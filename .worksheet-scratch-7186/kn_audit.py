from pathlib import Path
from openpyxl import load_workbook
from collections import Counter
import re,json,warnings
warnings.filterwarnings('ignore',category=UserWarning)
p=Path.home()/'Downloads'/'CHZ-laptops.xlsx'
w=load_workbook(p,data_only=True)
s=w['Данные']
print(s.max_row,s.max_column, [(c.column_letter,c.value,s.cell(5,c.column).value) for c in s[3]])
required=[c.column_letter for c in s[5] if c.value=='Да']
print('blanks', [(c,sum(s[f'{c}{i}'].value is None for i in range(7,s.max_row+1))) for c in required])
print('distribution', {c:Counter(str(s[f'{c}{i}'].value) for i in range(7,s.max_row+1)).most_common(5) for c in ('H','I','N','P','AG')})
fields=('C','D','F','G','R','T','U','V','W','X','Y','Z')
data=[]
for i in range(7,s.max_row+1):
 name=s[f'F{i}'].value
 if not name: continue
 vals={c:s[f'{c}{i}'].value for c in ('B',*fields)}
 missing=[c for c in fields if vals[c] is None]
 if missing:
  data.append(dict(row=i,missing=missing,**vals))
print('tech missing',len(data))
for d in data:
 print(d['row'],','.join(d['missing']),d['C'],d['D'],d['F'])
Path('kn_missing.json').write_text(json.dumps(data,ensure_ascii=False),encoding='utf-8')
