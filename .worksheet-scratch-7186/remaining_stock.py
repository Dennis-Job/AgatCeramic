from pathlib import Path
from openpyxl import load_workbook
from collections import defaultdict
import re,warnings
warnings.filterwarnings('ignore',category=UserWarning,message='Data Validation extension')
root=Path.cwd()
downloads=Path.home()/'Downloads'
stock=load_workbook(downloads/'Текущий склад ЧЗ на 181126 вер.2.xlsx',data_only=True).active
def norm(x): return ' '.join(str(x).casefold().split())
books=[*sorted((root/'outputs'/'phones-181126').glob('*.xlsx')),*sorted((root/'outputs'/'warehouse-181126').glob('*.xlsx'))]
inventory={}
sku_index=defaultdict(list)
matched=set()
for r in range(8,stock.max_row+1):
 a=stock[f'A{r}'].value
 d=stock[f'D{r}'].value
 quantity=stock[f'F{r}'].value
 if not isinstance(d,str): continue
 title=re.split(r'\s*\|\|\s*|\s+(?=Intel Core Ultra 5 225H,)',d,maxsplit=1)[0].strip()
 k=norm(re.sub(r'^ноутбук\s+','',title,flags=re.I))
 if k not in inventory:
  inventory[k]={'rows':[],'art':a,'title':title,'desc':d,'qty':0}
 inventory[k]['rows'].append(r)
 inventory[k]['qty']+=quantity or 0
 if a: sku_index[norm(a)].append(k)
for p in books:
 s=load_workbook(p,read_only=True,data_only=True)['Данные']
 print('BOOK',p.name,s.max_row-6)
 for r in s.iter_rows(min_row=7,min_col=3,max_col=6,values_only=True):
  sku,_,_,label=r
  k=norm(re.sub(r'^ноутбук\s+','',label,flags=re.I))
  if k in inventory: matched.add(k)
  elif norm(sku) in sku_index and len(set(sku_index[norm(sku)]))==1: matched.update(sku_index[norm(sku)])
  else: print('UNMATCHED BOOK',sku,label)
print('stock',len(inventory),'rows',sum(len(x['rows']) for x in inventory.values()),'matched',len(matched),'unmatched',len(set(inventory)-matched))
for k,v in inventory.items():
 if k not in matched: print(v)
s=load_workbook(downloads/'Текущий склад ЧЗ на 181126.xlsx',data_only=True).active
print('ORIGINAL')
for r in range(7,s.max_row+1):
 c=s[f'A{r}']
 if c.alignment.indent<=4 and c.value: print(r,c.value,s[f'E{r}'].value)
mark=load_workbook(downloads/'Товары, подлежащие маркировке.xlsx',data_only=True,read_only=True).active
print('MARK CODES')
for row in mark.values:
 if row[0] and (re.search(r'домофон|панел.*вызыв|видеонаблюд|видеокамер|зам[ок].*электро|контрол.*доступ',str(row[2]),re.I) or str(row[0]).startswith(('8517','8525','8301'))):
  print(row[:4])
