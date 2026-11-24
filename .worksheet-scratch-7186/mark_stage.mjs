
import fs from "node:fs/promises";
import path from "node:path";
import {createRequire} from "node:module";
import {pathToFileURL} from "node:url";
const deps = path.join(process.env.USERPROFILE,".cache","codex-runtimes","codex-primary-runtime","dependencies","node");
const req = createRequire(path.join(deps,"runtime.cjs"));
const {FileBlob,SpreadsheetFile,Workbook} = await import(pathToFileURL(req.resolve("@oai/artifact-tool")).href);
const formats = JSON.parse(await fs.readFile("mark_rows.json","utf8"));
for (const {name,slug,rows} of formats) {
  const book = await SpreadsheetFile.importXlsx(await FileBlob.load(path.join(process.env.USERPROFILE,"Downloads",name)));
  const sheet = book.worksheets.getItem("Данные");
  if (slug === "laptops" || slug === "tablets") {
    const before = await book.render({sheetName:"Данные",range:"C5:F9",scale:0.65,format:"png"});
    await fs.writeFile(`mark-before-${slug}.png`,new Uint8Array(await before.arrayBuffer()));
  }
  for (const row of rows) {
    const [day,month,year] = row[3].split(".").map(Number);
    row[3] = (Date.UTC(year,month-1,day) - Date.UTC(1899,11,30))/86400000;
  }
  sheet.getRangeByIndexes(6,1,rows.length,rows[0].length).values = rows;
  sheet.getRange(`E7:E${6+rows.length}`).setNumberFormat("dd.mm.yyyy");
  sheet.getRange(`B7:B${6+rows.length}`).setNumberFormat("0");
  book.recalculate();
  const sample=await book.inspect({kind:"table",range:"Данные!C7:G9",include:"values,formulas",tableMaxRows:3,tableMaxCols:5});
  const errors=await book.inspect({kind:"match",searchTerm:"#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",options:{useRegex:true,maxResults:10}});
  console.log(slug,rows.length,sample.ndjson,errors.ndjson);
  const staged=await SpreadsheetFile.exportXlsx(book);
  await staged.save(`mark-staged-${slug}.xlsx`);
  const after=await book.render({sheetName:"Данные",range:"C5:G12",scale:0.7,format:"png"});
  await fs.writeFile(`mark-after-${slug}.png`,new Uint8Array(await after.arrayBuffer()));
}
const review=JSON.parse(await fs.readFile("mark_review.json","utf8"));
const workbook=Workbook.create();
const header=["КодТовара","Категория выгрузки","Наименование из выгрузки","Организация","Строка источника","Шаблон","Строка шаблона","Что проверить","Пустые обязательные поля"];
for(const [name,records] of [
  ["В шаблонах",review.filter(row=>row[5]!==null)],
  ["Нужна форма или коды",review.filter(row=>row[5]===null)]
]){
  const sheet=workbook.worksheets.add(name);
  sheet.getRange("A1:I1").values=[header];
  sheet.getRangeByIndexes(1,0,records.length,header.length).values=records;
  sheet.showGridLines=false;
  sheet.freezePanes.freezeRows(1);
  sheet.getRange("A1:I1").format.fill="#233847";
  sheet.getRange("A1:I1").format.font.color="#FFFFFF";
  sheet.getRange("A1:I1").format.font.bold=true;
  sheet.getRange("A1:I1").format.rowHeight=28;
  for(const [column,w] of [["A",16],["B",25],["C",59],["D",29],["E",23],["F",29],["G",17],["H",55],["I",56]]){
    sheet.getRange(`${column}:${column}`).format.columnWidth=w;
  }
  sheet.getRange(`A2:I${records.length+1}`).format.verticalAlignment="center";
  sheet.getRange(`A2:I${records.length+1}`).format.rowHeight=53;
  sheet.getRange(`B2:I${records.length+1}`).format.wrapText=true;
  for (const col of ["A","F","G"]) sheet.getRange(`${col}2:${col}${records.length+1}`).format.fill="#EDF3F7";
}
workbook.recalculate();
const sample=await workbook.inspect({kind:"table",range:"'Нужна форма или коды'!A1:I4",include:"values,formulas",tableMaxRows:4,tableMaxCols:9});
console.log(sample.ndjson);
const image=await workbook.render({sheetName:"Нужна форма или коды",range:"A1:C7",scale:1,format:"png"});
await fs.writeFile("mark-review-preview.png",new Uint8Array(await image.arrayBuffer()));
const response=await SpreadsheetFile.exportXlsx(workbook);
await fs.mkdir("outputs/mark-311026",{recursive:true});
await response.save("outputs/mark-311026/CHZ-sverka.xlsx");
