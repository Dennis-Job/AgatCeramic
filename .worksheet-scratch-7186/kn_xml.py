from pathlib import Path
from zipfile import ZipFile
from openpyxl import load_workbook
import re,warnings
warnings.simplefilter('ignore')
p=Path.home()/'Downloads'/'CHZ-laptops.xlsx'
s=load_workbook(p,data_only=True)['Данные']
for i in (7,13,14,15,16,17,19,21,90,268,274,285,299,300,302,346,373,397,449,477,478,565,587,772,813,822):
 print(i,[(s[c+str(i)].value) for c in 'CRUVWXYZ'])
with ZipFile(p) as z:
 x=z.read('xl/worksheets/sheet1.xml').decode()
 print(x[:340]); print(re.search(r'<row r="7".*?</row>',x)[0][:2300]); print(x[-280:]);print(re.findall(r'<x14:dataValidations count="(\d+)"',x))
