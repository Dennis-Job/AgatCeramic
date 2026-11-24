from collections import Counter
from pathlib import Path
from openpyxl import load_workbook
import json, warnings
warnings.filterwarnings('ignore',category=UserWarning,message='Data Validation extension')
p=Path('outputs')/'mark-311026'
report=load_workbook(p/'CHZ-sverka.xlsx',data_only=True)
mapping=json.loads(Path('mark_review.json').read_text(encoding='utf-8'))
assert sum(s.max_row-1 for s in report)==len(mapping)==1127
assert [s.max_row-1 for s in report]==[1055,72]
assert all(s.max_column==9 for s in report)
assert list(report.worksheets[0].values)[1:]==[tuple(row) for row in mapping if row[5] is not None]
assert list(report.worksheets[1].values)[1:]==[tuple(row) for row in mapping if row[5] is None]
print('Review',[(s.title,s.max_row-1) for s in report])
gtins=Counter()
for k in p.glob('*.xlsx'):
 if 'sverka' in k.name:continue
 s=load_workbook(k,read_only=True,data_only=True)['Данные']
 print(k.name,s.max_row-6,[(c,sum(1 for x in s.iter_rows(min_row=7,min_col=c,max_col=c,values_only=True) if x[0] is not None)) for c in (2,3,4,5)])
 for x in s.iter_rows(min_row=7,min_col=2,max_col=2,values_only=True):
  if x[0]:gtins[x[0]]+=1
print('GTINs',sum(gtins.values()),'duplicates',[code for code,n in gtins.items() if n>1])
assert len(gtins)==sum(gtins.values())==20
