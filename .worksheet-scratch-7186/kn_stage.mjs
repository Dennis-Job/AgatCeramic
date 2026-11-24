
import fs from "node:fs/promises";
import path from "node:path";
import {createRequire} from "node:module";
import {pathToFileURL} from "node:url";
const root=path.join(process.env.USERPROFILE,".cache","codex-runtimes","codex-primary-runtime","dependencies","node");
const req=createRequire(path.join(root,"runtime.cjs"));
const {FileBlob,SpreadsheetFile,Workbook}=await import(pathToFileURL(req.resolve("@oai/artifact-tool")).href);
const {values,audit,gaps}=JSON.parse(await fs.readFile("kn_updates.json","utf8"));
const wb=await SpreadsheetFile.importXlsx(await FileBlob.load(path.join(process.env.USERPROFILE,"Downloads","CHZ-laptops.xlsx")));
const sheet=wb.worksheets.getItem("Данные");
const render=async (name,range,file,book=wb)=>{
 const png=await book.render({sheetName:name,range,scale:0.8,format:"png"});
 await fs.writeFile(file,new Uint8Array(await png.arrayBuffer()));
};
try { await render("Данные","B5:F17","kn-before.png"); } catch(e) { console.log("Input preview:",e.message); }
for(const [address,value] of Object.entries(values)){
  const cell=sheet.getRange(address);
  cell.values=[[value]];
}
wb.recalculate();
const inspected=await wb.inspect({kind:"table",range:"Данные!B7:D17",include:"values,formulas",tableMaxRows:11,tableMaxCols:3});
const bad=await wb.inspect({kind:"match",searchTerm:"#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",options:{useRegex:true,maxResults:10}});
console.log(inspected.ndjson,bad.ndjson);
await (await SpreadsheetFile.exportXlsx(wb)).save("kn-staged.xlsx");
try { await render("Данные","B6:F18","kn-after.png"); } catch(e) { console.log("Output preview:",e.message); }
const report=Workbook.create();
const formatSheet=(name,headers,rows,sizes)=>{
 const tab=report.worksheets.add(name);
 const last=String.fromCharCode(64+headers.length);
 tab.getRangeByIndexes(0,0,1,headers.length).values=[headers];
 tab.getRangeByIndexes(1,0,rows.length,headers.length).values=rows;
 tab.freezePanes.freezeRows(1);
 tab.showGridLines=false;
 const heading=tab.getRange(`A1:${last}1`);
 heading.format.fill="#253B4F";
 heading.format.font.color="#FFFFFF";
 heading.format.font.bold=true;
 heading.format.rowHeight=25;
 const body=tab.getRange(`A2:${last}${rows.length+1}`);
 body.format.verticalAlignment="center";
 body.format.wrapText=true;
 body.format.rowHeight=39;
 sizes.forEach((size,i)=>{
  const col=String.fromCharCode(65+i);
  tab.getRange(`${col}:${col}`).format.columnWidth=size;
 });
 tab.getRange(`A2:B${rows.length+1}`).format.fill="#EDF3F7";
};
formatSheet("Что добавлено",["Строка","Код товара в выгрузке","Наименование","Поле","Значение","Где найдено","Ссылка на источник"],audit,[11,21,52,33,26,35,70]);
formatSheet("Осталось проверить",["Строка","Код товара в выгрузке","Наименование","Пропуски в характеристиках","Примечание"],gaps,[11,21,53,43,72]);
report.recalculate();
await render("Осталось проверить","A1:E8","kn-report.png",report);
await fs.mkdir("outputs/kns-laptops",{recursive:true});
await (await SpreadsheetFile.exportXlsx(report)).save("outputs/kns-laptops/CHZ-laptops-sources.xlsx");
