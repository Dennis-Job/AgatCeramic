from pathlib import Path
from zipfile import ZipFile
import re
for p in (Path.home()/'Downloads').glob('REP*-template(30.06.2026).xlsx'):
 with ZipFile(p) as z:
  w=z.read('xl/worksheets/sheet1.xml').decode()
  st=z.read('xl/styles.xml').decode()
  print(p.name,re.search('<dimension[^>]+',w).group(),'<numFmts' in st, re.findall(r'<x14:dataValidations count="(\d+)"',w))
