from pathlib import Path
from collections import Counter, defaultdict
import re,warnings
from openpyxl import load_workbook
warnings.filterwarnings('ignore',category=UserWarning)
p=Path.home()/'Downloads'
s=load_workbook(p/'Текущий склад ЧЗ на 181126 вер.2.xlsx',data_only=True).active
t=load_workbook(p/'REPLAPTOPS-2-template(30.06.2026) (1).xlsx',data_only=True)['Справочники']
print([x[0] for x in t.iter_rows(min_row=2,max_row=46,min_col=8,max_col=8,values_only=True)])
groups=defaultdict(list)
for row in range(8,s.max_row+1):
 part=s[f'A{row}'].value; title=s[f'D{row}'].value
 if not title: continue
 m=re.match(r'(?:Ноутбук )?(Acer|Apple|ASUS|Colorful|Dell|Honor|HP|Huawei|Infinix|Irbis|Lenovo|MSI|Rikor|Maibenben|Gigabyte|Digma|Chuwi|Thunderobot)\b',title,re.I)
 if not m: continue
 fragments=re.split(r'\s*\|\|\s*|\s+(?=Intel Core Ultra 5 225H,)',title,maxsplit=1)
 if len(fragments)<2:
  print('missing split',row,part,title)
  continue
 name,spec=fragments
 groups[name.strip()].append((row,part,spec))
 color=re.search(r',\s*([^,]+),\s*\d+(?:[.,]\d+)?\s*кг',spec,re.I)
 if not color or part is None or not color.group(1).strip().isalpha() or not title.startswith('Ноутбук '):
  print('special',row,part, name, spec, color.group(1) if color else '-')
print('groups',len(groups))
for k,v in groups.items():
 if len(v)>1: print('duplicate',k,v)
