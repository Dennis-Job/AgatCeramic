from collections import Counter
from openpyxl import load_workbook
from pathlib import Path
from random import Random
import re
s=load_workbook(Path.home()/'Downloads'/'markiruemye_tovary_CHZ.xlsx',data_only=True)
for cat in ['Ноутбук','Смартфон','Планшет','Телефон DECT / проводной / IP','Мобильный (кнопочный) телефон']:
 rows=[r for tab in list(s)[1:] for r in list(tab.values)[1:] if r[5]==cat]
 print('\n',cat,len(rows),'pipe',sum('||' in r[4] for r in rows),'discount',sum('уценк' in r[4].lower() for r in rows))
 for meaningful in (True,False):
  group=[r for r in rows if ('||' in r[4])==meaningful]
  rand=Random(42)
  for r in rand.sample(group,min(10 if cat in ('Ноутбук','Смартфон','Планшет') else 30,len(group))):
   print(r[2],r[4])
