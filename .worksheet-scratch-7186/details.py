from pathlib import Path
from zipfile import ZipFile
from openpyxl import load_workbook
from collections import Counter
import re,warnings
warnings.filterwarnings('ignore',category=UserWarning)
p=Path.home()/'Downloads'
a=load_workbook(p/'Текущий склад ЧЗ на 181126.xlsx',data_only=True).active
b=load_workbook(p/'Текущий склад ЧЗ на 181126 вер.2.xlsx',data_only=True).active
q=load_workbook(p/'REPLAPTOPS-2-template(30.06.2026)-заполнен.xlsx',data_only=True).active
cat=False; selects=[]
for i in range(7,a.max_row+1):
 c=a[f'A{i}']; x=c.value
 if not x: continue
 if c.alignment.indent<=4: cat=x=='Ноутбуки'
 if cat and '||' in x: selects.append((i,x,a[f'E{i}'].value))
 if cat and not '||' in x and (len(x)>40 or i in range(44,51)): print('base misc',i,c.style_id,x)
print('base count',len(selects),len(set(x[1] for x in selects)),sum(x[2] for x in selects))
print('with product printed: RAM etc')
for r in range(8,231):
 e=b[f'D{r}'].value
 if not e:continue
 if 'Apple MacBook' in e or re.search(r'21MLA02LCD|5301ALXN|5301AQHF|90NB10N3|G614PR|21NYSC5G00',e):
  print(r,b[f'A{r}'].value,e)
for r in range(7,q.max_row+1):
 if re.search(r'21MLA02LCD|5301ALXN|5301AQHF|90NB10N3|G614PR|21NYSC5G00|Apple MacBook',q[f'F{r}'].value or ''):
  print('old',r, [q[f'{col}{r}'].value for col in 'CDFRUVWXYZ'])
with ZipFile(p/'REPLAPTOPS-2-template(30.06.2026) (1).xlsx') as z:
 xml=z.read('xl/worksheets/sheet1.xml').decode()
 print('col config',xml.split('<sheetData')[0][-3500:])
 print('styles_xfs',z.read('xl/styles.xml').decode().split('<cellXfs',1)[1].split('</cellXfs>')[0][:4000])
