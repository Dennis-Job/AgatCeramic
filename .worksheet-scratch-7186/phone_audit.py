from pathlib import Path
from openpyxl import load_workbook
from zipfile import ZipFile
from collections import Counter
import warnings, re
warnings.filterwarnings('ignore', category=UserWarning)
p=Path.home()/'Downloads'
for path in sorted(p.glob('REP*-заполнен.xlsx')):
 if 'LAPTOPS' in path.name: continue
 print('\nFILE',path.name)
 w=load_workbook(path, data_only=True)
 s=w['Данные']
 print('rows',s.max_row,'sheets',w.sheetnames)
 for c in range(1,s.max_column+1):
  h=s.cell(3,c); status=s.cell(5,c).value
  vals=Counter(s.cell(r,c).value for r in range(7,s.max_row+1))
  print(h.column_letter,h.value,status,s.cell(6,c).value, list(vals.items()) if len(vals)<7 else vals.most_common(3), 'blank',vals[None])
  if status=='Да':
   print('  rule',s.cell(4,c).value)
 print('data',*[ (r,[(c.column_letter,c.value) for c in s[r] if c.value is not None]) for r in range(7,s.max_row+1)],sep='\n')
 with ZipFile(path) as z:
  xml=z.read('xl/worksheets/sheet1.xml').decode()
  print('validation',re.findall(r'<x14:dataValidations count="(\d+)"',xml))
 print('dictionary columns')
 for col in w['Справочники'].iter_cols():
  if col[0].value is not None: print(col[0].value,[c.value for c in col[1:] if c.value is not None][:9])
