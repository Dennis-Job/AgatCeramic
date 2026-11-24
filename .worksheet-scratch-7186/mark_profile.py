from collections import Counter,defaultdict
from openpyxl import load_workbook
from pathlib import Path
from zipfile import ZipFile
import warnings
warnings.filterwarnings('ignore',category=UserWarning,message='Data Validation extension')
p=Path.home()/'Downloads'
wb=load_workbook(p/'markiruemye_tovary_CHZ.xlsx',data_only=True)
for s in wb:
 print('\nTAB',s.title,s.max_row,s.max_column)
 data=list(s.values)
 if s.title=='Сводка':
  for row in data:print(row)
  continue
 print('header',data[0])
 print('nonempty',[(i,sum(r[i] is not None for r in data[1:])) for i in range(s.max_column)])
 groups=defaultdict(list)
 for r in data[1:]:
  if r[2] is not None:groups[r[5]].append(r)
 for k,rr in groups.items():
  print(k,'lines',len(rr),'distinct internal codes',len(set(r[2] for r in rr)),'unique descriptions',len(set(r[4] for r in rr)), 'by org',Counter(r[1] for r in rr))
  for r in rr[:4]: print(r[2:6],r[8:])
 print('duplicate IDs + descriptions conflicts',sum(1 for k in set(r[2] for r in data[1:]) if len(set(r[4] for r in data[1:] if r[2]==k))>1))
with ZipFile(p/'markiruemye_tovary_CHZ.zip') as z:
 print('related ZIP',z.namelist())
