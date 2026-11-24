from pathlib import Path
from collections import Counter,defaultdict
from zipfile import ZipFile
from xml.sax.saxutils import escape
from openpyxl import load_workbook
from openpyxl.utils.cell import column_index_from_string
import re,json,warnings
warnings.simplefilter("ignore",UserWarning)

incoming=Path.home()/"Downloads"/"CHZ-laptops.xlsx"
data=json.loads(Path("kn_updates.json").read_text(encoding="utf-8"))
target=Path("outputs")/"kns-laptops"/"CHZ-laptops-KNS.xlsx"
trace=target.with_name("CHZ-laptops-sources.xlsx")
target.parent.mkdir(parents=True,exist_ok=True)
values=data["values"]
worksheet="xl/worksheets/sheet1.xml"
staged=load_workbook("kn-staged.xlsx",data_only=True)["Данные"]
by_row=defaultdict(dict)
for addr,wanted in values.items():
    assert staged[addr].value==wanted,(addr,staged[addr].value,wanted)
    m=re.fullmatch(r"([A-Z]+)(\d+)",addr)
    by_row[int(m[2])][m[1]]=staged[addr].value

def xml_cell(col,num,val,style=""):
    addr=f"{col}{num}"
    if isinstance(val,str):
        return f'<c r="{addr}"{style} t="inlineStr"><is><t>{escape(val)}</t></is></c>'
    assert isinstance(val,(int,float))
    return f'<c r="{addr}"{style}><v>{val}</v></c>'

count=Counter()
row_pattern=re.compile(r'(<row r="(\d+)"[^>]*>)(.*?)(</row>)',re.S)
cell_pattern=re.compile(r'<c r="([A-Z]+)(\d+)"[^>]*?(?:/>|>.*?</c>)',re.S)
def update_row(m):
    num=int(m[2])
    if num not in by_row:return m[0]
    required=by_row[num]
    cells={c[1]:c[0] for c in cell_pattern.finditer(m[3])}
    assert "".join(cells.values())==m[3],num
    for k,val in required.items():
        old=cells.get(k)
        style=""
        if old is not None:
            assert old.endswith("/>") or not re.search(r"<(?:v|f|is)>",old),old
            keep=re.search(r' s="\d+"',old)
            style=keep[0] if keep else ""
        cells[k]=xml_cell(k,num,val,style)
        count[k]+=1
    return m[1]+"".join(cells[k] for k in sorted(cells,key=column_index_from_string))+m[4]

with ZipFile(incoming) as original, ZipFile(target,"w") as final:
    before=original.read(worksheet).decode("utf-8")
    after=row_pattern.sub(update_row,before)
    assert sum(count.values())==len(values)
    assert before.split("</sheetData>")[1]==after.split("</sheetData>")[1]
    assert '<x14:dataValidations count="11"' in after
    for part in original.infolist():
        final.writestr(part,after.encode("utf-8") if part.filename==worksheet else original.read(part))

with ZipFile(incoming) as original, ZipFile(target) as final:
    assert final.testzip() is None
    assert original.namelist()==final.namelist()
    assert {n for n in final.namelist() if original.read(n)!=final.read(n)}=={worksheet}

source=load_workbook(incoming)
output=load_workbook(target)
s0=source["Данные"]
s1=output["Данные"]
found={}
for row_a,row_b in zip(s0.iter_rows(),s1.iter_rows(),strict=True):
    for a,b in zip(row_a,row_b,strict=True):
        if a.value!=b.value:
            assert a.value in (None,"") and b.coordinate in values
            assert b.data_type!='f'
            found[b.coordinate]=b.value
assert found==values
assert s1.max_row==s0.max_row==826 and s1.max_column==s0.max_column==33
assert source.sheetnames==output.sheetnames
assert all(s1[f"B{i}"].data_type=="s" and len(s1[f"B{i}"].value)==13 for i in range(7,827) if f"B{i}" in values)
assert all(s1[f"E{i}"].value==s0[f"E{i}"].value for i in range(7,827))
r=load_workbook(trace,data_only=True)
assert list(r["Что добавлено"].values)[1:]==[tuple(a) for a in data["audit"]]
assert list(r["Осталось проверить"].values)[1:]==[tuple(a) for a in data["gaps"]]
print("Counts",dict(count))
print("GTIN left",sum(s1[f"B{i}"].value in (None,"") for i in range(7,827)))
print("Files",target.resolve(),target.stat().st_size,trace.resolve(),trace.stat().st_size)
