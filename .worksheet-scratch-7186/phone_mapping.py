from pathlib import Path
from collections import Counter
from openpyxl import load_workbook
import json,re,warnings

warnings.filterwarnings('ignore', category=UserWarning, message='Data Validation extension')
downloads=Path.home()/'Downloads'
stock=load_workbook(downloads/'Текущий склад ЧЗ на 181126 вер.2.xlsx',data_only=True).active
stock_index={}
def normalize(s):
    return ' '.join(str(s).strip().casefold().split())

for row in stock.iter_rows(min_row=8, max_col=6, values_only=True):
    sku, _, _, description, _, quantity = row
    if sku is not None and isinstance(description,str) and '||' in description:
        title = description.split('||',1)[0].strip()
        stock_index[(normalize(sku),normalize(title))] = description

# Separate items need a supplier code because this particular template offers
# only the wired telephone / cordless handset classifications.
needs_codes = {'KX-TGA681RUB','S30852-H2862-S301','W610H','W620W Black'}
labels = {
    'KX-TGA681RUB': 'Дополнительная трубка DECT Panasonic KX-TGA681RUB',
    'S30852-H2862-S301': 'Дополнительная трубка DECT Gigaset CL660HX',
    'W610H': 'Беспроводная трубка Fanvil W610H',
    'W620W Black': 'Переносной Wi-Fi-телефон Fanvil W620W Black',
    'L30250-F600-C151': 'Цифровой системный телефон Siemens OpenStage 40 T Lava'
}
def valid_ean(s):
    weights = (3 if i % 2 == 0 else 1 for i in range(len(s) - 1))
    check = sum(int(d)*w for d,w in zip(reversed(s[:-1]),weights))
    return (10-check%10)%10 == int(s[-1])

templates = []
seen = set()
for prefix, num in [('REPMOBILEPHONES',17),('REPSMARTPHONES',12),('REPSTATIONARYPHONES',12),('REPTABLETS',6)]:
    source_name=f'{prefix}-2-template(30.06.2026).xlsx'
    orig=load_workbook(downloads/source_name,data_only=True)
    partial=load_workbook(downloads/f'{prefix}-2-template(30.06.2026)-заполнен.xlsx',data_only=True)['Данные']
    main=orig['Данные']
    dictionary=orig['Справочники']
    assert main.max_row==6 and partial.max_row==num+6
    for row in range(1,7):
        assert [c.value for c in main[row]] == [c.value for c in partial[row]]
    draft_cols = { 'H':'A','I':'B','K':'C','L':'D','M':'E','N':'F','P':'G','R':'H' }
    if prefix in ('REPSMARTPHONES','REPTABLETS'):
        draft_cols.update(T='I',V='J')
    enumerations = {c: {x.value for x in dictionary[col][1:] if x.value}
                    for c,col in draft_cols.items()}
    columns = [c.column_letter for c in main[5] if c.value=='Да']
    rows=[]
    for r in range(7,partial.max_row+1):
        sku=partial[f'C{r}'].value
        candidate=partial[f'F{r}'].value
        title_key=normalize(candidate)
        matches={data for (stock_sku, stock_title),data in stock_index.items() if
            stock_title==title_key and
            (normalize(sku)==stock_sku or re.search(r'(?<!\w)'+re.escape(str(sku))+r'(?!\w)',data,re.I))}
        assert len(matches)==1, (sku,candidate,matches)
        description=matches.pop()
        assert sku not in seen
        seen.add(sku)
        vals={c:partial[f'{c}{r}'].value for c in columns}
        vals.update(E='19.11.2026',H='<UNID> НЕ УКАЗАН',I='<1990> НЕ УКАЗАН',N=None,P=None)
        if sku in labels:
            vals['F']=labels[sku]
        if sku in needs_codes:
            for c in ('K','L','M'):
                vals[c]=None
        if sku=='SM-F741BZYGCAU':
            vals.update(X='Qualcomm',Y='Snapdragon 8 Gen 3 for Galaxy')
        barcode_candidates = set(re.findall(r'(?<!\d)\d{13}(?!\d)',description))
        valid_codes = sorted(s for s in barcode_candidates if valid_ean(s))
        assert len(valid_codes)<=1
        if valid_codes:
            vals['B'] = int(valid_codes[0])
        for col, choices in enumerations.items():
            assert vals[col] is None or vals[col] in choices, (sku,col,vals[col])
        for col, limit in {'C':50,'D':128,'F':250,'G':128,'X':128,'Y':128}.items():
            if col in vals:
                assert isinstance(vals[col],str) and 0<len(vals[col])<=limit,(sku,col,vals[col])
        rows.append([vals.get(c.column_letter) for c in main[3][1:]])
    blanks={main[f'{c}3'].value:sum(row[main[f'{c}3'].column-2] is None for row in rows) for c in columns}
    print(prefix,num,{k:v for k,v in blanks.items() if v},'GTIN',[(r[1],r[0]) for r in rows if r[0] is not None])
    templates.append(dict(name=source_name,prefix=prefix,rows=rows))
assert len(seen)==47
Path('phone_rows.json').write_text(json.dumps(templates,ensure_ascii=False),encoding='utf-8')
