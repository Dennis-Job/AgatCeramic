from pathlib import Path
from collections import Counter
from openpyxl import load_workbook
from zipfile import ZipFile
import re, warnings
warnings.filterwarnings('ignore',category=UserWarning)
p=Path.home()/'Downloads'
for basename in ('Текущий склад ЧЗ на 181126.xlsx','Текущий склад ЧЗ на 181126 вер.2.xlsx'):
 s=load_workbook(p/basename,data_only=True).active
 print('\n',basename,'hidden',[(k,v.hidden) for k,v in s.column_dimensions.items()], 'merged',str(s.merged_cells)[:400])
 for r in range(7,s.max_row+1):
  row=[c.value for c in s[r]]
  if r<42 or not isinstance(row[0],str) or not re.match(r'ноутбук\b',row[0],re.I) and r%10==0: print(r,row)
 print('prefixes',Counter(str(row[0]).split()[0] if row[0] else None for row in s.values).most_common(40))
 print('styles',Counter((s.cell(r,1).style_id,s.cell(r,4).style_id,s.cell(r,1).alignment.indent) for r in range(7,s.max_row+1)))
s=load_workbook(p/'REPLAPTOPS-2-template(30.06.2026)-заполнен.xlsx',data_only=True).active
for i in range(2,27):
 print(s.cell(3,i).value,Counter(s.cell(r,i).value for r in range(7,s.max_row+1)).most_common(12))
print('output first items',list(s.values)[6:13])
with ZipFile(p/'REPLAPTOPS-2-template(30.06.2026) (1).xlsx') as z:
 for n in ['xl/_rels/workbook.xml.rels','xl/worksheets/sheet1.xml','xl/worksheets/sheet2.xml']:
  xml=z.read(n).decode(); print(n,xml[:500],xml[-2400:])
