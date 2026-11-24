from pathlib import Path
import re,warnings
from collections import Counter
from openpyxl import load_workbook
warnings.filterwarnings('ignore',category=UserWarning)
p=Path.home()/'Downloads'
s=load_workbook(p/'Текущий склад ЧЗ на 181126.xlsx',data_only=True).active
t=load_workbook(p/'Текущий склад ЧЗ на 181126 вер.2.xlsx',data_only=True).active
q=load_workbook(p/'REPLAPTOPS-2-template(30.06.2026)-заполнен.xlsx',data_only=True).active
def norm(x): return ' '.join(x.casefold().split())
def split(s):
 s=re.sub(r'^ноутбук\s+', '', s, flags=re.I)
 return re.split(r'\s*\|\|\s*|\s+(?=(?:Intel|AMD|Apple) .{2,50},\s*(?:\d+[.,]\d+\s*GHz|\d+\s+\w+ть? ядер|\d+\s*(?:Mb|Gb)))',s,maxsplit=1)[0].strip()
base=[]
cat=False
brand=None
for r in range(7,s.max_row+1):
 c=s[f'A{r}']; name=c.value; indent=c.alignment.indent
 if not name: continue
 if indent<=4: cat=name=='Ноутбуки'; brand=None
 elif cat and indent==6: brand=name
 elif cat and (('||' in name) or c.style_id in (29,30,31,32,33)):
  if '||' in name or s.row_dimensions[r+1].outlineLevel<=s.row_dimensions[r].outlineLevel:
   base.append((r,brand,name,s[f'E{r}'].value))
v2=[(r,t[f'A{r}'].value,t[f'D{r}'].value,t[f'F{r}'].value) for r in range(8,231) if t[f'D{r}'].value]
v2_laptops=[x for x in v2 if x[2].startswith('Ноутбук ') or any(norm(split(y[2]))==norm(split(x[2])) for y in base)]
old={norm(re.sub(r'^Ноутбук ','',q[f'F{r}'].value or '')):r for r in range(7,q.max_row+1)}
bkeys={norm(split(x[2])) for x in base}
vkeys={norm(split(x[2])) for x in v2_laptops}
print('counts',len(base),len(bkeys),sum(x[3] or 0 for x in base),len(v2_laptops),len(vkeys),sum(x[3] or 0 for x in v2_laptops),len(old))
for label,col,other in [('base-only',base,vkeys),('v2-only',v2_laptops,bkeys)]:
 for x in col:
  if norm(split(x[2])) not in other: print(label,x)
for k,n in Counter(norm(split(x[2])) for x in base).items():
 if n>1:
  for x in base:
   if norm(split(x[2]))==k: print('base dup',x)
for x in v2_laptops:
 if len(x[2])<=100 or '||' not in x[2] or not re.search(r'\d+[.,]?\d*\s*(?:Gb|Tb|Гб|Тб)\s*(?:SSD|HDD)',x[2],re.I):
  print('v2 short/special',x)
print('nonlaptop prefixes',Counter(v[2].split()[0] for v in v2 if v not in v2_laptops))
print('RAM patterns',Counter(re.split(r'\s*\|\|\s*',x[2],maxsplit=1)[-1].split(',')[2].strip() for x in v2_laptops if x[2].count(',')>3))
