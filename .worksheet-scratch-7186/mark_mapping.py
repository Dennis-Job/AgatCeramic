from pathlib import Path
from collections import Counter, defaultdict
from openpyxl import load_workbook
import re, json, warnings, csv

warnings.filterwarnings("ignore",category=UserWarning,message="Data Validation extension")
downloads=Path.home()/"Downloads"
book=load_workbook(downloads/"markiruemye_tovary_CHZ.xlsx",data_only=True)
templates = {
    "laptops":("REPLAPTOPS-2-template(30.06.2026) (1).xlsx","Ноутбуки"),
    "smartphones":("REPSMARTPHONES-2-template(30.06.2026).xlsx","Смартфоны"),
    "mobilephones":("REPMOBILEPHONES-2-template(30.06.2026).xlsx","Мобильные и проводные телефоны"),
    "stationaryphones":("REPSTATIONARYPHONES-2-template(30.06.2026).xlsx","Радиотелефоны"),
    "tablets":("REPTABLETS-2-template(30.06.2026).xlsx","Планшеты")
}
basecats={
    "Ноутбук":"laptops", "Смартфон":"smartphones", "Планшет":"tablets",
    "Мобильный (кнопочный) телефон":"mobilephones"
}
previous={key:[] for key in templates}
for parent in ("warehouse-181126","phones-181126"):
    for p in (Path("outputs")/parent).glob("*.xlsx"):
        key=next(k for k in templates if p.name.startswith("REP"+k.upper()+"-"))
        wb=load_workbook(p,data_only=True)
        s=wb["Данные"]
        previous[key].extend({c.column_letter:c.value for c in row if c.value is not None}
                            for row in s.iter_rows(min_row=7))
types = {"laptops":"Ноутбук","smartphones":"Смартфон","tablets":"Планшет",
         "mobilephones":"Телефон","stationaryphones":"Радиотелефон"}
category_codes = {
    "laptops":("8471300000","26.20.11.110","7130000001"),
    "smartphones":("8517130000","26.30.22.110","7170000001"),
    "tablets":(None,None,"7140000001"),
    "mobilephones":(None,None,"7180000007"),
    "stationaryphones":(None,None,"7160000003")
}
brands="Acer|Apple|ASUS|Blackview|CBR|Chuwi|Colorful|Dell|DEXP|Digma Pro|Digma|Doogee|Fplus|Fujitsu|Gigabyte|Gigaset|Grandstream|Haier|Hasee|Hiper|Honor|HP|Huawei|Infinix|Inoi|Irbis|Itel|Kvadra|Lenovo|LG|Maibenben|Microsoft|Motorola|MSI|Nokia|OnePlus|Oppo|Oukitel|Panasonic|Philips|Prestigio|Realme|Redmi|Rikor|Ritmix|Razer|Samsung|Tecno|Thunderobot|Toshiba|Ulefone|Vivo|Xiaomi|Yealink|Fanvil|ZTE|POCO|OUKITEL|Nothing|Teclast|Unisoc|AOC|Osio|Machcreator|Hydravana|NEC|Sony|Alldocube|Oscal|IIIF150|iQOO|F+|EliteGroup|Machenike|iRU|ADATA"
brand_map={x.casefold():x for x in brands.split("|")}
def norm(s):
    return " ".join(str(s).casefold().replace("ё","е").split())
def num(s):
    n=float(s.replace(",","."))
    return int(n) if n.is_integer() else n
def unique(seq):
    return list(dict.fromkeys(seq))
def good_ean(text):
    return (10-sum(int(n)*(3 if i%2==0 else 1) for i,n in enumerate(reversed(text[:-1])))%10)%10==int(text[-1])
def sizeunit(unit):
    return {"m":"m","м":"m","g":"g","г":"g","t":"t","т":"t"}[unit.casefold()[0]]
def match_part(s):
    return bool(re.fullmatch(r"[A-Z0-9][A-Z0-9./_+-]{3,42}(?:[ -](?:CTO|ENG|RUS|WIN11PRO|WIN11P|WINPRO|PRO|BLACK|WHITE))?",s,re.I)
                and re.search(r"[A-Za-z]",s) and re.search(r"\d",s)
                and not re.search(r"^\d+(?:gb|mb|tb|ghz)$|^(?:Windows|Android|Bluetooth|MacOS|Win11)",s,re.I))
def safe_model(s,part):
    s=re.split(r" [,;]|,\s|\s+(?=(?:Intel|AMD)\s)|\s+(?=(?:i[3579][ -]?\d{4}|Ultra [579] \d|Ryzen\b))",s,maxsplit=1,flags=re.I)[0].strip(" ,[]")
    if part:
        s=re.sub(r"(?:\s+|\s*\[|\s*\()"+re.escape(str(part))+r"(?:\]|\))?$","",s,flags=re.I)
    return s[:128].strip() or None
def strip_heading(s):
    names=r"(?:(?:\d{1,2}(?:[.,]\d+)?\s*['”\"])\s+)?(?:(?:Ноутбук|Смартфон|Планшет|Радиотелефон(?:\s+DECT)?|Мобильный телефон|Телефон сотовый|IP[- ]телефон|Телефон(?:\s+IP)?|Сетевое оборудование)\s+)+"
    return re.sub("^"+names,"",s,flags=re.I).strip()
def split_title(s):
    fragments=re.split(r"\s*\|\|\s*|\s+(?=Intel Core Ultra 5 225H,)",s,maxsplit=1)
    return (fragments+[""])[:2]
def capacity(s):
    return (num(s[0]),sizeunit(s[1]))
def from_mbytes(n,u):
    return (n//1024,"g") if u=="m" and n%1024==0 else (n,u)
def apple_ram(model):
    # Capacity by iPhone model from Xcode device records and published teardowns.
    m=re.match(r"iphone\s+(air|1[1-7])(?:\s+(pro(?:\s+max)?|plus|mini))?(?=\b)",model or "",re.I)
    if not m or re.match(r"iphone\s+1[67]e\b",model,re.I): return None
    gen,variant=m.group(1).lower(),(m.group(2) or "").lower()
    if gen=="air": return 12
    if gen=="11": return 4
    if gen in ("12","13"): return 6 if variant.startswith("pro") else 4
    if gen=="14": return 6
    if gen=="15": return 8 if variant.startswith("pro") else 6
    return 12 if gen=="17" and variant.startswith("pro") else 8
def color_found(head,spec,colors):
    aliases={
        "серебристый":"серебряный", "серебристая":"серебряный", "black":"черный","white":"белый",
        "grey":"серый", "gray":"серый","silver":"серебряный","purple":"сиреневый/лиловый",
        "pink":"розовый","blue":"синий","green":"зеленый","red":"красный",
        "gold":"золотой","yellow":"желтый","orange":"оранжевый","beige":"бежевый","brown":"коричневый",
        "teal":"бирюзовый","sky blue":"голубой","space gray":"серый","space grey":"серый",
        "light gray":"светло-серый","dark gray":"темно-серый","dark grey":"темно-серый","deep blue":"темно-синий",
        "титан черный":"черный","черный титан":"черный","белый титан":"белый","лаванда":"сиреневый/лиловый",
        "песочный":"бежевый","изумрудный зеленый":"зеленый","серый космос":"серый",
        "розовое золото":"розово-бежевый","черный и белый":"черный/белый","черный - белый":"черный/белый"
    }
    def find_color(raw):
        raw=norm(re.sub(r"\s*[\[(].*$","",raw.strip()))
        raw=re.sub(r"^цвет\s*:?\s*","",raw)
        return colors.get(aliases.get(raw,raw))
    for part in reversed(re.split(r",\s+",spec)):
        c=find_color(part)
        if c:return c
    clean=norm(head)
    candidates=sorted(set(colors)|set(aliases),key=len,reverse=True)
    for name in candidates:
        if name!="нет в справочнике" and re.search(r"(?<![\w-])"+re.escape(name)+r"(?![\w-])",clean):
            return colors.get(aliases.get(name,name))
    return None
def cpu_found(head,spec,kind):
    sample=spec[:170] if spec else head
    parts=[x.strip() for x in re.split(r",\s+(?!\d+\s*ядер)",sample)[:2]]
    explicit=r"(?i)^(Intel|AMD|Apple|Qualcomm|MediaTek|Unisoc|HiSilicon|Samsung|Rockchip|Spreadtrum|Allwinner|Amlogic)\s+(.+)"
    inferred=[(r"\b(Snapdragon [\w+ -]+)","Qualcomm"),(r"\b((?:Dimensity|Helio) [\w+ -]+)","MediaTek"),
              (r"\b(Exynos [\w+ -]+)","Samsung"),(r"\b(Kirin [\w+ -]+)","HiSilicon"),
              (r"\b(A1[0-9](?:X|Z)?(?:\s+(?:Pro|Bionic|Fusion))?|M[1-5](?:\s+(?:Pro|Max|Ultra))?)\b","Apple"),
              (r"\b((?:Core(?: Ultra)? [3579]|Core i[3579]|i[3579])[- ]+\d[\w]+)\b","Intel"),
              (r"\b(Ryzen(?: AI)? [3579] (?:Pro )?\d[\w]+)\b","AMD"),
              (r"\b(MT\d{4}[A-Z]?)\b","MediaTek")]
    def tidy(x):
        x=re.split(r"\s+(?:\d+(?:[.,]\d+)?\s*(?:[GM]Hz|ГГц)|\d+(?:[.,]\d+)?\s*(?:GB|MB)|(?:DDR|LPDDR)[0-9]|Intel (?:UHD|Iris|Arc))",x,maxsplit=1,flags=re.I)[0]
        return x.strip(" ,;/")
    for part in parts:
        m=re.match(explicit,part) if spec else None
        if m:
            model=tidy(m[2])
            if model and not re.match(r"^(?:Graphics|Arc|Iris|UHD|Radeon|GPU)\b",model,re.I):
                return m[1],model[:128]
        for pattern,brand in inferred:
            if brand=="Apple" and kind=="laptops" and not re.search(r"macbook|apple",head,re.I):continue
            if brand=="Apple" and kind in ("tablets","smartphones") and not re.search(r"\b(iphone|ipad|apple)\b",head,re.I):continue
            m=re.search(pattern,part,re.I)
            if m:
                model=tidy(m[1])[:128]
                if model:return brand,model
    return None,None

sources=defaultdict(list)
for sheet in list(book)[1:]:
    for rownum,r in enumerate(list(sheet.values)[1:],2):
        assert r[2] and r[4] and r[5]
        sources[r[2]].append((sheet.title,rownum,r))
processed=defaultdict(list)
review=[]
coverage=Counter()
brand_unknown=Counter()
facts_used=Counter()
for stock_code, entries in sources.items():
    rawtab,rowno,r=entries[0]
    cat=r[5]
    description=r[4].strip()
    title,spec=split_title(description)
    title=re.split(r"\s+уценка\b",title,maxsplit=1,flags=re.I)[0].strip()
    title0=strip_heading(title)
    kind=basecats.get(cat)
    if cat=="Телефон DECT / проводной / IP":
        if re.search(r"радиотелефон|дополнительная трубка|беспроводн(?:ая|ой)|\bDECT\b",title+" "+spec,re.I) and not re.search(r"\bпроводной VoIP",spec,re.I):
            kind="stationaryphones"
        else: kind="mobilephones"
    group_names={"Лампа (светодиодная / накаливания)":"Источники света",
                 "Светильник / ночник / прожектор":"Светильники",
                 "Фонарь":"Портативные фонари",
                 "Чай":"Бакалейная продукция — чай",
                 "Сетевой фильтр / удлинитель / розетка":"Штепсели и розетки — после сверки кодов",
                 "Антибактериальные салфетки / антисептик":"Антисептики / дезинфицирующие средства — после сверки кодов"}
    source_positions="; ".join(f"{t}!{n}" for t,n,_ in entries)
    organizations="; ".join(unique(a[2][1] for a in entries))
    if not kind:
        review.append([stock_code,cat,description,organizations,source_positions,None,None,
                       group_names[cat],"ТН ВЭД, ОКПД2 и форма по товарной группе"])
        coverage[cat]+=1
        continue

    if len([x for x in entries if x[2][4]!=description or x[2][5]!=cat])>0:
        raise ValueError((stock_code,entries))
    if not processed.get(kind):
        w=load_workbook(downloads/templates[kind][0],data_only=True)
        processed[kind]=[w["Данные"],w["Справочники"],[]]
    ws,dct,filled=processed[kind]
    valnames={c.column_letter:c.value for c in ws[3] if c.value}
    mandatory=[c.column_letter for c in ws[5] if c.value=="Да"]
    def entry(col,code):
        if not code:return None
        return next(c.value for c in dct[col][1:] if c.value and c.value.startswith(f"<{code}>"))
    before=norm(title0)
    previous_matches=[x for x in previous[kind] if norm(strip_heading(x["F"]))==before]
    old=previous_matches[0] if len(previous_matches)==1 else {}
    actual_brand=next((p for p in brand_map if before==p or before.startswith(p+" ")),None)
    brand=brand_map[actual_brand] if actual_brand else old.get("G")
    if not brand:brand_unknown[title0.split(" ")[0]]+=1
    short=title0[len(actual_brand):].strip() if actual_brand else title0
    pnum=old.get("C")
    if not pnum:
        explicit=re.search(r"(?i)\b(?:p/n|part\s*number|арт(?:икул)?\.)\s*[:#-]?\s*([A-Z0-9][A-Z0-9./_+-]{3,42})",title+" "+spec)
        if explicit and match_part(explicit[1]):pnum=explicit[1]
    if not pnum:
        for part in reversed(re.split(r",\s+",spec)[1:]):
            part=re.split(r"\s*(?:уценка|\s\(|\.\s)",part,maxsplit=1,flags=re.I)[0].strip(" .")
            if match_part(part):
                pnum=part
                break
    if not pnum:
        models=re.findall(r"\b(?:SM-[A-Z0-9]{5,}|[A-Z0-9]{4,}/A|90N[A-Z0-9-]{10,}|[KN][HX]\.[A-Z0-9.]{6,})\b",title,re.I)
        if len(set(models))==1:pnum=models[0]
    if not pnum and kind in ("mobilephones","stationaryphones"):
        mark=re.search(r"\b(?:GRP[0-9A-Z]+|GXP[0-9A-Z]+|SIP-[0-9A-Z]+|KX-[0-9A-Z]+|V\d{2}(?: Pro|[A-Z])?|W\d{2,3}[A-Z]|X\d[A-Z]+)\b",title,re.I)
        if mark:pnum=mark[0]

    model=old.get("D") if old else None
    if not model:
        model=safe_model(short,pnum)
        if kind in ("smartphones","tablets"):
            model=re.split(r"\s+(?=\d+/\d+\s*(?:GB|ГБ)?\b|\d+\s*(?:GB|TB|ГБ|ТБ)\b)",model or "",maxsplit=1,flags=re.I)[0].strip() or None
    if not model:
        model=(short[:128].strip() or None)
    if kind=="mobilephones" and brand=="Philips" and model and re.match(r"E2602\b",model):
        model="Xenium E2602"
    if kind=="laptops" and brand=="Apple" and model:
        model=re.split(r'\s+(?=8-Core|10-Core|\d+GB|p/n)',model,maxsplit=1,flags=re.I)[0]
    if not brand: model=None
    label=old.get("F") if old else None
    if not label:
        label=title if norm(title).startswith(norm(types[kind])+" ") else (types[kind]+" "+title0)
        label=label[:250].strip()
    colors={norm(c.value.split("> ",1)[1]):c.value for c in dct["H"][1:] if c.value}
    colr=color_found(title0,spec,colors) or old.get("R")
    if colr and colr not in colors.values():colr=None
    kcode,lcode,typecode=category_codes[kind]
    if kind=="mobilephones":
        if cat=="Мобильный (кнопочный) телефон":
            kcode="8517140000";lcode="26.30.22.110"
            typecode="7180000005" if re.search(r"раскладн|Nokia 2660",description,re.I) else "7180000007"
        else:
            typecode="7180000013" if re.search(r"VoIP|SIP|IP[- ]телефон",description,re.I) else "7180000009"
    if kind=="stationaryphones":
        if re.search(r"беспроводная трубка|дополнительная трубка|беспроводной VoIP",spec,re.I): typecode=None
        elif re.search(r"VoIP|IP[- ]телефон",description,re.I): typecode="7160000004"
    if kind=="smartphones" and re.search(r"\b(?:Galaxy Z (?:Fold|Flip)|Razr|Magic V[0-9]|Mate X[0-9])\b",title0,re.I):
        typecode="7170000005"
    if kind=="tablets" and re.search(r"\bзащищ[её]нн",description,re.I):
        typecode="7140000003"
    forename=r"(?<![0-9])\d{13}(?![0-9])"
    eans=unique(x for x in re.findall(forename,description) if good_ean(x))
    gtin= int(eans[0]) if len(eans)==1 else None
    if old.get("B") and str(old["B"]).isdigit() and (not eans or str(old["B"]) in eans):
        gtin=old["B"]
    vals={"B":gtin,"C":pnum,"D":model,"E":"19.11.2026","F":label,"G":brand,
          "H":entry("A","UNID"),"I":entry("B","1990"),"K":entry("C",kcode),
          "L":entry("D",lcode),"M":entry("E",typecode),"R":colr}
    if kind in ("laptops","smartphones","tablets"):
        required_cpu=cpu_found(title0,spec,kind)
        display=spec or title0
        diagonal=re.search(r'(?<![\d.])(\d{1,2}(?:[.,]\d+)?)\s*["”″]',display)
        screen=num(diagonal[1]) if diagonal else None
        if screen and not ((7 if kind=="laptops" else 2)<=screen<=21):screen=None
        if kind=="laptops":
            storage_candidates=[]
            drive=re.compile(r"(?i)(?:(\d+(?:[.,]\d+)?)\s*(Mb|Gb|Tb|Мб|Гб|Тб)\s*(?:SSD|HDD|eMMC)|(?:SSD|HDD|eMMC)\s*(\d+(?:[.,]\d+)?)\s*(Mb|Gb|Tb|Мб|Гб|Тб))")
            for m in drive.finditer(display):
                storage_candidates.append(capacity((m[1],m[2]) if m[1] else (m[3],m[4])))
            disk=storage_candidates[0] if len(storage_candidates)==1 else None
            if not disk and diagonal:
                suffix=display[diagonal.end():]
                m=re.search(r",\s*(\d+(?:[.,]\d+)?)\s*(Gb|Tb|Гб|Тб)(?=\s*(?:,|$))",suffix,re.I)
                if m and num(m[1])>=128:disk=capacity(m.groups())
            begin=display[:diagonal.start()] if diagonal else display[:100]
            mem=re.findall(r"(?i)(?<![.\w])(\d+)\s*(Mb|Gb|Мб|Гб)\b",begin)
            ram=from_mbytes(*capacity(mem[0])) if len(mem)==1 else None
            if not ram:
                m=re.search(r"(?:LPDDR|DDR)\d\w*\s+(\d+)\s*(Gb|Гб)\b",display,re.I)
                if m:ram=capacity(m.groups())
        else:
            pair=re.search(r"\b(\d{1,2})\s*/\s*(\d{2,4})\s*(GB|ГБ|TB|ТБ)?\b",title0,re.I)
            basic=[]
            for part in re.split(r",\s+",spec):
                m=re.fullmatch(r"\s*(\d+(?:[.,]\d+)?)\s*(Mb|Gb|Tb|Мб|Гб|Тб)\s*",part,re.I)
                if m:basic.append(capacity(m.groups()))
            if len(basic)==2:
                ram,disk=basic
            elif pair and 1<=int(pair[1])<=32 and int(pair[2])>=16:
                ram=(int(pair[1]),"g");disk=(int(pair[2]),sizeunit(pair[3]) if pair[3] else "g")
            elif len(basic)==1:
                ram=None;disk=basic[0]
            else:
                size=re.search(r"\b(\d+)\s*(Gb|Tb|Гб|Тб)\b",title0,re.I)
                disk=capacity(size.groups()) if size and (int(size[1])>=16 or sizeunit(size[2])=="t") else None
                ram=None
            if kind=="smartphones" and brand=="Apple" and not ram:
                n=apple_ram(model)
                if n:
                    ram=(n,"g");facts_used["Xcode RAM"]+=1
        if kind=="laptops":
            if ram and ram[1]=="g" and ram[0]>128:
                ram=None
            if disk and ((disk[1]=="g" and disk[0]<32) or disk[1]=="m"):
                disk=None
        if not ram and old.get("U"):
            ram=(old["U"],"g" if "Гбайт" in old["T"] else "m")
        if not disk and old.get("W"):
            disk=(old["W"],"t" if "Тбайт" in old["V"] else "g" if "Гбайт" in old["V"] else "m")
        if not screen:screen=old.get("Z")
        process_brand,process_model=required_cpu
        if not process_brand or not process_model:
            process_brand=old.get("X")
            process_model=old.get("Y")
        if process_brand=="НЕТ ДАННЫХ" or process_model=="НЕТ ДАННЫХ":
            process_brand=process_model=None
        if ram and ((ram[1]=="m" and not 0<ram[0]<=65536) or (ram[1]=="g" and not 0<ram[0]<=256)):ram=None
        vals.update(T=entry("I","7110000002" if ram[1]=="g" else "7110000001") if ram else None, U=ram[0] if ram else None,
                    V=entry("J",{"m":"7120000001","g":"7120000002","t":"7120000003"}[disk[1]]) if disk else None,W=disk[0] if disk else None,
                    X=process_brand,Y=process_model,Z=screen)
    if vals.get("X") and vals.get("Y"):
        vals["Y"]=vals["Y"][:128].strip()
    if len(eans)>1:gtin_note="Несколько GTIN в описании"
    elif not gtin:gtin_note="GTIN сверить с этикеткой"
    else:gtin_note=""
    note=[]
    if len(entries)>1: note.append("Повтор по КодТовара объединён")
    if rawtab=="Проверить":note.append("Сверить ТН ВЭД и ОКПД2 с документами")
    if re.search(r"\bуценк|\bвитринн|\bб/у\b",description,re.I): note.append("Проверить состояние и применимость маркировки")
    if gtin_note:note.append(gtin_note)
    gaps=[valnames[c] for c in mandatory if not vals.get(c)]
    coverage[kind]+=1
    if kind=="stationaryphones" and typecode is None:note.append("Отдельная трубка или беспроводной аппарат. Уточнить форму после сверки кодов")
    review.append([stock_code,cat,description,organizations,source_positions,templates[kind][1],len(filled)+7,
                  "; ".join(note) or None,", ".join(gaps) or None])
    filled.append([vals.get(c.column_letter) for c in ws[3][1:]])

result=[]
for k,(w,d,rows) in processed.items():
    mandatory={c.column:w.cell(3,c.column).value for c in w[5] if c.value=="Да"}
    blanks={h:sum(a[col-2] is None or a[col-2]=="" for a in rows) for col,h in mandatory.items()}
    print(k,len(rows),blanks)
    result.append({"slug":k,"name":templates[k][0],"out":f"CHZ-{k}.xlsx","rows":rows})
print("coverage",coverage,"brands",brand_unknown,"fact_sources",facts_used,"review",len(review))
assert len(review)==1127 and sum(len(r["rows"]) for r in result)+sum(1 for a in review if not a[5])==1127
Path("mark_rows.json").write_text(json.dumps(result,ensure_ascii=False),encoding="utf-8")
Path("mark_review.json").write_text(json.dumps(review,ensure_ascii=False),encoding="utf-8")
