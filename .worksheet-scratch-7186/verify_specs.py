import json
from pathlib import Path
from openpyxl import load_workbook
p=Path.home()/'Downloads'
old=load_workbook(p/'REPLAPTOPS-2-template(30.06.2026)-заполнен.xlsx',data_only=True).active
by_sku={r[2]:r for r in list(old.values)[6:]}
new=json.loads(Path('rows.json').read_text(encoding='utf-8'))
for r in new:
 o=by_sku.get(r[0])
 if o is None: print('new code',r[:5]); continue
 diffs=[(chr(i+67),o[i+2],r[i]) for i in (1,4,15,17,18,20,21,22,23) if r[i] is not None and o[i+2]!=r[i]]
 if diffs: print(r[0],diffs)
