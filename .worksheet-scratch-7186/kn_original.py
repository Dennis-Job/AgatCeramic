from pathlib import Path
from openpyxl import load_workbook
import json,re,warnings
warnings.simplefilter('ignore')
p=Path.home()/'Downloads'
old=load_workbook(Path('outputs')/'mark-311026'/'CHZ-laptops.xlsx',data_only=True)['Данные']
now=load_workbook(p/'CHZ-laptops.xlsx',data_only=True)['Данные']
print('diff',[(r,c,old[f'{c}{r}'].value,now[f'{c}{r}'].value) for r in range(7,827) for c in ('B','C','D','F','G','R','T','U','V','W','X','Y','Z') if old[f'{c}{r}'].value!=now[f'{c}{r}'].value][:30])
source=load_workbook(p/'markiruemye_tovary_CHZ.xlsx',data_only=True)
review=json.loads(Path('mark_review.json').read_text(encoding='utf-8'))
rmap={r[6]:(r[0],r[2]) for r in review if r[5]=='Ноутбуки'}
for row in json.loads(Path('kn_missing.json').read_text(encoding='utf-8')):
 r=row['row']
 print(r,rmap[r][0],','.join(row['missing']),rmap[r][1])
Path('kn_raw.json').write_text(json.dumps(rmap,ensure_ascii=False),encoding='utf-8')
