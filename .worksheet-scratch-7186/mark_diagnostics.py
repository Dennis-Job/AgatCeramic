import json
from pathlib import Path
from collections import Counter,defaultdict
r=json.loads(Path("mark_rows.json").read_text(encoding="utf-8"))
for item in r:
 z=item["rows"]; print("\n",item["slug"],"gtins",[(a[0],a[1],a[4][:120]) for a in z if a[0]])
 print("brands",Counter(a[5] for a in z))
 print("model weird",[(a[1],a[2],a[4][:120]) for a in z if not a[2] or len(a[2])>55 or a[2]==a[1]][:45])
 if item["slug"] in ("laptops","smartphones","tablets"):
  print("amounts",[Counter((a[b],a[b+1]) for a in z) for b in (18,20)])
  print("CPUs",Counter(a[22] for a in z),[(a[1],a[22],a[23]) for a in z if a[23] and (len(a[23])>=30 or "16"==a[23])][:60])
 print("article weird", [(a[1],a[4][:120]) for a in z if a[1] and (len(str(a[1]))>30 or "os"==str(a[1]).lower() or "Win" in str(a[1]))][:48])
