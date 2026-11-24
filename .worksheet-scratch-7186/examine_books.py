from pathlib import Path
from collections import Counter
from openpyxl import load_workbook
from zipfile import ZipFile
p=Path.home()/'Downloads'
names=['REPLAPTOPS-2-template(30.06.2026) (1).xlsx','Текущий склад ЧЗ на 181126.xlsx','Текущий склад ЧЗ на 181126 вер.2.xlsx','REPLAPTOPS-2-template(30.06.2026)-заполнен.xlsx']
for name in names:
 f=p/name
 print('\nFILE', name)
 with ZipFile(f) as z: print(z.namelist())
 w=load_workbook(f)
 for s in w:
  print(s.title, s.max_row,s.max_column, 'validations',[(str(v.sqref),v.formula1) for v in s.data_validations.dataValidation][:40])
  print('comments',[(c.coordinate,c.comment.text[:900]) for c in s._cells.values() if c.comment][:40])
  print('has rows',sum(any(c.value is not None for c in row) for row in s.iter_rows()))
  for row in s.iter_rows(max_row=min(7,s.max_row)):
   print(' | '.join(f'{c.coordinate}[{c.style_id}]={str(c.value)[:600]}' for c in row if c.value is not None))
