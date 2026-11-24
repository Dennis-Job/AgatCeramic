from datetime import datetime
from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZipFile
import json,re,warnings

from openpyxl import load_workbook
from openpyxl.utils import get_column_letter
from openpyxl.utils.datetime import to_excel

warnings.filterwarnings('ignore', category=UserWarning, message='Data Validation extension')
sheet_file='xl/worksheets/sheet1.xml'
style_file='xl/styles.xml'
output_dir=Path('outputs')/'mark-311026'
output_dir.mkdir(exist_ok=True,parents=True)

def cell_xml(address,value,style_index):
    if isinstance(value,datetime):
        return f'<c r="{address}" s="{style_index}"><v>{int(to_excel(value))}</v></c>'
    if isinstance(value,(int,float)):
        style = f' s="{style_index+1}"' if address.startswith("B") else ""
        return f'<c r="{address}"{style}><v>{value}</v></c>'
    assert isinstance(value,str)
    return f'<c r="{address}" t="inlineStr"><is><t>{escape(value)}</t></is></c>'

for item in json.loads(Path('mark_rows.json').read_text(encoding='utf-8')):
    original=Path.home()/'Downloads'/item['name']
    path=output_dir/item['out']
    staged=load_workbook('mark-staged-'+item['slug']+'.xlsx',data_only=True)['Данные']
    unformatted=item['rows']
    values=list(staged.iter_rows(min_row=7,max_row=6+len(unformatted),min_col=2,max_col=len(unformatted[0])+1,values_only=True))
    for raw,val in zip(unformatted,values,strict=True):
        raw[3]=datetime.strptime(raw[3],'%d.%m.%Y')
        assert tuple(raw)==val, (raw,val)
    with ZipFile(original) as source, ZipFile(path,'w') as target:
        sheet=source.read(sheet_file).decode()
        styles=source.read(style_file).decode()
        assert '<numFmts' not in styles
        styles=styles.replace('<fonts ','<numFmts count="1"><numFmt numFmtId="164" formatCode="dd.mm.yyyy"/></numFmts><fonts ',1)
        num=int(re.search(r'<cellXfs count="(\d+)"',styles).group(1))
        styles=styles.replace(f'<cellXfs count="{num}"',f'<cellXfs count="{num+2}"',1)
        styles=styles.replace('</cellXfs>','<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>',1)
        new_rows=[]
        for r,row in enumerate(values,7):
            cells=''.join(cell_xml(f'{get_column_letter(c)}{r}',v,num) for c,v in enumerate(row,2) if v is not None)
            new_rows.append(f'<row r="{r}" spans="2:{len(row)+1}" x14ac:dyDescent="0.25">{cells}</row>')
        final=sheet.replace('</sheetData>',''.join(new_rows)+'</sheetData>',1)
        final,n=re.subn(r'(<dimension ref="A1:[A-Z]+)6"/>',lambda m:f'{m.group(1)}{6+len(values)}"/>',final,count=1)
        assert n==1
        for part in source.infolist():
            replacements={sheet_file:final.encode(),style_file:styles.encode()}
            target.writestr(part,replacements[part.filename] if part.filename in replacements else source.read(part))
    with ZipFile(original) as source, ZipFile(path) as target:
        assert target.testzip() is None
        assert source.namelist()==target.namelist()
        assert {part for part in source.namelist() if source.read(part)!=target.read(part)}=={style_file,sheet_file}
        original_sheet=source.read(sheet_file).decode()
        filled_sheet=target.read(sheet_file).decode()
        assert original_sheet.split('</sheetData>')[1]==filled_sheet.split('</sheetData>')[1]
        assert '<x14:dataValidations count=' in filled_sheet
    verify=load_workbook(path,data_only=True)['Данные']
    assert verify.max_row==6+len(values)
    assert list(verify.iter_rows(min_row=7,min_col=2,max_col=len(values[0])+1,values_only=True))==values
    assert all(verify[f'E{r}'].number_format=='dd.mm.yyyy' for r in range(7,verify.max_row+1))
    assert all(verify[f'B{r}'].value is None or verify[f'B{r}'].number_format=='0' for r in range(7,verify.max_row+1))
    print(path.resolve(),len(values),path.stat().st_size)
