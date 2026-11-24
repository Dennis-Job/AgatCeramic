from datetime import datetime
from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZipFile
import json
import re
import warnings

from openpyxl import load_workbook
from openpyxl.utils import get_column_letter
from openpyxl.utils.datetime import to_excel

warnings.filterwarnings('ignore', category=UserWarning, message='Data Validation extension')
template = Path.home() / 'Downloads' / 'REPLAPTOPS-2-template(30.06.2026) (1).xlsx'
output = Path('outputs') / 'warehouse-181126' / 'REPLAPTOPS-2-zapolnen.xlsx'
output.parent.mkdir(exist_ok=True, parents=True)
sheet_file = 'xl/worksheets/sheet1.xml'
style_file = 'xl/styles.xml'
staged = load_workbook('staged.xlsx', data_only=True)['Данные']
expected = json.loads(Path('rows.json').read_text(encoding='utf-8'))
values = list(staged.iter_rows(min_row=7, max_row=6+len(expected), min_col=3, max_col=26, values_only=True))
assert len(values) == len(expected)
for row, staged_row in zip(expected, values):
    row[2] = datetime.strptime(row[2], '%d.%m.%Y')
    assert tuple(row) == staged_row, (row, staged_row)

def cell_xml(address, value, style_index):
    if isinstance(value, datetime):
        return f'<c r="{address}" s="{style_index}"><v>{int(to_excel(value))}</v></c>'
    if isinstance(value, (int,float)):
        return f'<c r="{address}"><v>{value}</v></c>'
    assert isinstance(value,str)
    return f'<c r="{address}" t="inlineStr"><is><t>{escape(value)}</t></is></c>'

with ZipFile(template) as source, ZipFile(output, 'w') as target:
    sheet = source.read(sheet_file).decode()
    styles = source.read(style_file).decode()
    assert '<numFmts' not in styles
    styles = styles.replace('<fonts ', '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd.mm.yyyy"/></numFmts><fonts ', 1)
    xfs_count = int(re.search(r'<cellXfs count="(\d+)"', styles).group(1))
    styles = styles.replace(f'<cellXfs count="{xfs_count}"', f'<cellXfs count="{xfs_count+1}"',1)
    date_style = '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
    styles = styles.replace('</cellXfs>',date_style+'</cellXfs>',1)
    new_rows = []
    for number,row in enumerate(values,7):
        cells = ''.join(cell_xml(f'{get_column_letter(col)}{number}', value, xfs_count)
                        for col,value in enumerate(row,3) if value is not None)
        new_rows.append(f'<row r="{number}" spans="3:26" x14ac:dyDescent="0.25">{cells}</row>')
    sheet_out = sheet.replace('</sheetData>',''.join(new_rows)+'</sheetData>',1)
    sheet_out = sheet_out.replace('ref="A1:AG6"', f'ref="A1:AG{6+len(values)}"',1)
    for part in source.infolist():
        data = {sheet_file:sheet_out.encode(), style_file:styles.encode()}.get(part.filename)
        target.writestr(part,source.read(part) if data is None else data)

with ZipFile(template) as source, ZipFile(output) as target:
    assert target.testzip() is None
    changed = [part for part in source.namelist() if source.read(part)!=target.read(part)]
    assert set(changed)=={sheet_file,style_file}
    original_sheet = source.read(sheet_file).decode()
    saved_sheet = target.read(sheet_file).decode()
    assert original_sheet.split('</sheetData>')[1] == saved_sheet.split('</sheetData>')[1]
    assert '<x14:dataValidations count="11"' in saved_sheet
final = load_workbook(output,data_only=True)['Данные']
assert list(final.iter_rows(min_row=7,min_col=3,max_col=26,values_only=True))==values
required = [c.column for c in final[5] if c.value=='Да']
missing = {final.cell(3,c).value:sum(final.cell(r,c).value is None for r in range(7,final.max_row+1)) for c in required}
print('Required blank counts', {field:n for field,n in missing.items() if n})
print('Missing technical attributes by row', [(r,final[f'C{r}'].value) for r in range(7,final.max_row+1) if any(final[f'{c}{r}'].value is None for c in ('R','V','W','Z'))])
print(output.resolve())
