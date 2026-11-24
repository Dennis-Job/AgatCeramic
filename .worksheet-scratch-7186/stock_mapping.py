from collections import Counter, defaultdict
from pathlib import Path
import json, re, warnings
from openpyxl import load_workbook

warnings.filterwarnings('ignore', category=UserWarning, message='Data Validation extension')
downloads = Path.home() / 'Downloads'
template = downloads / 'REPLAPTOPS-2-template(30.06.2026) (1).xlsx'
original = load_workbook(downloads / 'Текущий склад ЧЗ на 181126.xlsx', data_only=True).active
stock = load_workbook(downloads / 'Текущий склад ЧЗ на 181126 вер.2.xlsx', data_only=True).active
dictionary = load_workbook(template, data_only=True)['Справочники']

def key(value):
    return ' '.join(value.casefold().replace('ё', 'е').split())

def dict_value(col, code):
    return next(x.value for x in dictionary[col] if isinstance(x.value, str) and x.value.startswith(f'<{code}>'))

def split_name(description):
    return re.split(r'\s*\|\|\s*|\s+(?=Intel Core Ultra 5 225H,)', description.strip(), maxsplit=1)

def without_type(name):
    return re.sub(r'^ноутбук\s+', '', name, flags=re.I)

original_names = set()
in_laptops = False
for row in original.iter_rows(min_row=7, max_col=1):
    cell = row[0]
    text = cell.value
    if not text:
        continue
    if cell.alignment.indent <= 4:
        in_laptops = text == 'Ноутбуки'
    fragments = split_name(text)
    if in_laptops and len(fragments) == 2:
        original_names.add(key(without_type(fragments[0])))

colors = {key(c.value.split('> ',1)[1]): c.value for c in dictionary['H'][1:46] if c.value}
color_aliases = {'серебристый': 'серебряный', 'серый космос': 'серый', 'синий (quiet blue)': 'синий'}
fallback_sku = {8:'90NX0981-M00E20', 9:'21SGA001CD CTO', 10:'21R3000WUS', 11:'9S7-17U111-029'}
all_products = defaultdict(list)
skipped = []
for sku_cell, _, _, description_cell, _, quantity_cell in stock.iter_rows(min_row=8,max_col=6):
    row = description_cell.row
    description = description_cell.value
    if not description:
        continue
    fragments = split_name(description)
    title = fragments[0]
    clean_name = without_type(title)
    if not re.match(r'^Ноутбук\s+',title,re.I) and key(clean_name) not in original_names:
        skipped.append((row,title))
        continue
    assert len(fragments) == 2, (row, description)
    spec = fragments[1]
    sku = str(sku_cell.value or fallback_sku[row]).strip()
    if row in fallback_sku:
        assert spec.strip().endswith(sku)
    all_products[(key(clean_name),key(sku))].append((row,sku,title,spec,quantity_cell.value))

results = []
missing = Counter()
unmatched_original = set(original_names)
by_position = {}
for (name_key, sku_key), entries in all_products.items():
    row, sku, title, spec, quantity = max(entries, key=lambda e: len(e[3]))
    unmatched_original.discard(name_key)
    clean_name = without_type(title)
    brand, _, model = clean_name.partition(' ')
    # The stock heading identifies the configuration even if the catalog part
    # number and the name use different suffixes.
    model = re.sub(r'\s+'+re.escape(sku)+r'$', '', model, flags=re.I)
    first_spec = spec.split(',',1)[0]
    cpu_brand, _, cpu = first_spec.partition(' ')
    assert cpu_brand in ('Intel', 'AMD', 'Apple'), (row, first_spec)
    ram = re.search(r',\s*(\d+)\s*(Mb|Gb|Мб|Гб)(?=\s*(?:,|$))',spec,re.I)
    disk = re.search(r'(?<!\w)(\d+(?:[.,]\d+)?)\s*(Gb|Tb|Гб|Тб)\s*(?:SSD|HDD)',spec,re.I)
    diagonal = re.search(r',\s*(\d{1,2}(?:[.,]\d+)?)\s*["”″]',spec)
    if row == 22 and not diagonal and spec.endswith(', 14'):
        screen = 14
    else:
        screen = float(diagonal.group(1).replace(',','.')) if diagonal else None
    color = re.search(r',\s*([^,]+),\s*\d+(?:[.,]\d+)?\s*кг',spec,re.I)
    actual_color = key(color.group(1)) if color else ''
    actual_color = color_aliases.get(actual_color, actual_color)
    ram_number = int(ram.group(1)) if ram else None
    ram_unit = key(ram.group(2)) if ram else ''
    if ram_unit in ('mb','мб') and ram_number % 1024 == 0:
        ram_number //= 1024
        ram_unit = 'gb'
    disk_unit = key(disk.group(2)) if disk else ''
    values = {
        'C':sku, 'D':model.strip(), 'E':'19.11.2026',
        'F':f'Ноутбук {clean_name}', 'G':brand,
        'H':dict_value('A','UNID'), 'I':dict_value('B','1990'),
        'K':dict_value('C','8471300000'), 'L':dict_value('D','26.20.11.110'),
        'M':dict_value('E','7130000001'),
        'R':colors.get(actual_color),
        'T':dict_value('I','7110000002' if ram_unit in ('gb','гб') else '7110000001') if ram else None,
        'U':ram_number,
        'V':dict_value('J','7120000003' if disk_unit in ('tb','тб') else '7120000002') if disk else None,
        'W':float(disk.group(1).replace(',','.')) if disk else None,
        'X':cpu_brand, 'Y':cpu, 'Z':screen
    }
    for col,val in values.items():
        if val is None:
            missing[col]+=1
            print('Missing',row,sku,col,spec)
    for col,limit in {'C':50,'D':128,'F':250,'G':128,'X':128,'Y':128}.items():
        assert isinstance(values[col],str) and 0<len(values[col])<=limit,(row,col,values[col])
    assert sku_key not in by_position, (row, sku, by_position.get(sku_key))
    by_position[sku_key]=row
    results.append([values.get(chr(col)) for col in range(ord('C'),ord('Z')+1)])
print('Unmatched in original',unmatched_original)
print('Extra in v2', [x[3] for x in results if key(without_type(x[3])) not in original_names])
print('Skipped',len(skipped),'duplicate warehouse lines',sum(len(e)-1 for e in all_products.values()),'unique',len(results),'missing',dict(missing))
assert len(results)==155
Path('rows.json').write_text(json.dumps(results,ensure_ascii=False),encoding='utf-8')
