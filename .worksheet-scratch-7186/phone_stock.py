from pathlib import Path
from openpyxl import load_workbook
from zipfile import ZipFile
from collections import Counter
import re,warnings
warnings.filterwarnings('ignore',category=UserWarning)
p=Path.home()/'Downloads'
for n in p.glob('REP*-template(30.06.2026).xlsx'):
 w=load_workbook(n,data_only=True)
 print(n.name, w['Данные'].max_row)
 t=w['Справочники']
 for c in 'CDE':
  print(c,[x.value for x in t[c] if x.value])
 for gx in ('A','B'):
  print(gx,[x.value for x in t[gx][1:] if x.value and ('НЕ УКАЗАН'in x.value or 'КОРОБКА' in x.value or 'КАРТОН' in x.value)])
s=load_workbook(p/'Текущий склад ЧЗ на 181126 вер.2.xlsx',data_only=True).active
print('STOCK')
for r in range(8,s.max_row+1):
 a=s[f'A{r}'].value; d=s[f'D{r}'].value
 if d and (not re.match(r'Ноутбук ',d) and not re.match(r'(Lenovo|Honor MagicBook|ASUS VivoBook)',d)):
  print(r,a,d)
for fname in ('Товары, подлежащие маркировке.xlsx','markiruemye_tovary_CHZ.xlsx'):
 w=load_workbook(p/fname,data_only=True,read_only=True)
 print(fname,[(s.title,s.max_row,s.max_column) for s in w])
 s=w.worksheets[0]
 for row in s.iter_rows(min_row=1,max_row=3,values_only=True):
  print(row)
