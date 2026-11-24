from collections import Counter, defaultdict
from pathlib import Path
from openpyxl import load_workbook
import json,re,warnings
warnings.simplefilter("ignore", UserWarning)

downloads=Path.home()/"Downloads"
file=downloads/"CHZ-laptops.xlsx"
book=load_workbook(file,data_only=True)
sheet=book["Данные"]
dct=book["Справочники"]
payload=json.loads(Path("kn_updates_input.json").read_text(encoding="utf-8"))
stock=json.loads(Path("kn_raw.json").read_text(encoding="utf-8"))
labels={sheet.cell(3,i).column_letter:sheet.cell(3,i).value for i in range(1,34)}
cols=("C","R","T","U","V","W","X","Y","Z")

def clean(s):
    return " ".join(str(s).replace("ё","е").casefold().split())
colors={clean(c.value.split("> ",1)[1]):c.value for c in dct["H"][1:] if c.value}
ramunit=next(c.value for c in dct["I"] if c.value and "Гбайт" in c.value)
driveunit=next(c.value for c in dct["J"] if c.value and "Гбайт" in c.value)
updates={}
audit=[]
keys=defaultdict(set)
for k in range(7,sheet.max_row+1):
    existing=sheet[f"B{k}"].value
    if existing:
        keys[str(existing)].add(k)

def add(row,col,value,source_url,source_label):
    address=f"{col}{row}"
    assert sheet[address].value in (None,"") and address not in updates, address
    updates[address]=value
    audit.append((row,stock[str(row)][0],sheet[f"F{row}"].value,labels[col],value,source_label,source_url))

def valid_gtin(s):
    return re.fullmatch(r"\d{13}",s) and (10-sum(int(c)*(3 if i % 2==0 else 1) for i,c in enumerate(reversed(s[:-1])))%10)%10==int(s[-1])

candidates={}
for key,values in payload["manual"].items():
    row=int(key)
    src=payload["sources"].get(key)
    source_name="KNS" if src and re.search(r"\.kns\.ru/",src) and "/product/" in src else "Карточка товара и складская выгрузка" if src else "Складская выгрузка"
    if row in (285,318,449,477,478):
        src=None
        source_name="Складская выгрузка"
    if row==373:
        source_name="ОЗУ и SSD из выгрузки, цвет по базовому артикулу"
        src="https://www.hp.com/emea_middle_east-en/products/laptops/product-details/product-specifications/2101620721"
        # The confirmed product-specific page was provided by the search results below.
        src=payload.get("source373")
    if row==565:
        source_name="Оттенок в складской выгрузке, цвет сверен по KNS"
    for col,val in values.items():
        if col=="B":
            candidates[row]=(val,src,source_name)
            continue
        if col=="R":
            val=colors[clean(val)]
        if col=="C":
            assert 1<=len(val)<=50
        add(row,col,val,src,source_name)
        if col=="U":
            add(row,"T",ramunit,src,source_name)
        if col=="W":
            add(row,"V",driveunit,src,source_name)
for key,item in payload["cands"].items():
    row=int(key)
    assert clean(sheet[f"C{row}"].value)==clean(item["pn"])
    if row in candidates and candidates[row][0]!=item["code"]:
        candidates.pop(row)
        continue
    candidates[row]=(item["code"],item["url"],item["url"].split("/")[2])
for row,(gtin,url,site) in candidates.items():
    assert valid_gtin(gtin)
    keys[gtin].add(row)
accepted=0
duplicates=0
for row,(gtin,url,site) in candidates.items():
    if len(keys[gtin])>1:
        article=clean(updates.get(f"C{row}",sheet[f"C{row}"].value))
        article_set={clean(updates.get(f"C{k}",sheet[f"C{k}"].value)) for k in keys[gtin]}
        if article_set!={article}:
            duplicates+=1
            continue
    add(row,"B",gtin,url,site)
    accepted+=1

gaps=[]
for row in range(7,sheet.max_row+1):
    missing=[labels[c] for c in cols if sheet[f"{c}{row}"].value in (None,"") and f"{c}{row}" not in updates]
    if not missing: continue
    note={
        277:"2174077 указан как код магазина. Артикул производителя не подтверждён.",
        285:"Индивидуальная конфигурация XCTO. Цвет и артикул по карточке KNS не подтверждены.",
        295:"Указана конфигурация CTO без отдельного артикула.",
        307:"Несколько комплектаций Lenovo/Dell с похожим названием. Нужен код конкретной позиции.",
        322:"Описание содержит конфигурацию без отдельного артикула.",
        332:"В карточке указано обозначение модели. Отдельный артикул не подтверждён.",
        336:"В карточке указано обозначение модели. Отдельный артикул не подтверждён.",
        337:"В карточке указано обозначение модели. Отдельный артикул не подтверждён.",
        449:"Модель процессора и артикул не подтверждены. В описании указан процессор AMD Ryzen 5 без полного номера.",
        477:"Найдено несколько близких комплектаций. Нужен артикул с упаковки.",
        478:"Найдено несколько близких комплектаций. Нужен артикул с упаковки.",
        491:"В карточке указано обозначение модели. Отдельный артикул не подтверждён.",
        492:"В карточке указано обозначение модели. Отдельный артикул не подтверждён.",
        587:"В складском описании указано NoRAM. Установленный объём памяти не подтверждён.",
    }.get(row,"Нужно подтвердить по карточке точной комплектации или документам поставщика.")
    note=note.replace("Lenovo/Dell","Dell")
    gaps.append([row,stock[str(row)][0],sheet[f"F{row}"].value,", ".join(missing),note])
counts=Counter(re.sub(r"\d+","",a) for a in updates)
print("FILLED",len(updates),dict(counts),"GTIN",accepted,"conflicts",duplicates, "remaining technical",len(gaps))
print("GAPS",[(g[0],g[3]) for g in gaps])
print("CHANGED ROWS",len({a[0] for a in audit}))
assert counts["U"]==counts["T"] and counts["V"]==counts["W"]
assert len(audit)==len(updates)
Path("kn_updates.json").write_text(json.dumps({"values":updates,"audit":sorted(audit,key=lambda a:(a[0],a[3])),"gaps":gaps},ensure_ascii=False),encoding="utf-8")
