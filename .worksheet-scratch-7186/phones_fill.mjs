import fs from "node:fs/promises";
import path from "node:path";
import {createRequire} from "node:module";
import {pathToFileURL} from "node:url";
const dependencies = path.join(process.env.USERPROFILE,".cache","codex-runtimes","codex-primary-runtime","dependencies","node");
const resolveRuntime = createRequire(path.join(dependencies,"runtime.cjs"));
const {FileBlob,SpreadsheetFile} = await import(pathToFileURL(resolveRuntime.resolve("@oai/artifact-tool")).href);
const templates = JSON.parse(await fs.readFile("phone_rows.json","utf8"));
for (const {name,prefix,rows} of templates) {
  const book = await SpreadsheetFile.importXlsx(await FileBlob.load(path.join(process.env.USERPROFILE,"Downloads",name)));
  const sheet = book.worksheets.getItem("Данные");
  const before = await book.render({sheetName:"Данные",range:"B5:G9",scale:0.6,format:"png"});
  await fs.writeFile(`before-${prefix}.png`,new Uint8Array(await before.arrayBuffer()));
  for (const row of rows) {
    const [day,month,year] = row[3].split(".").map(Number);
    row[3] = (Date.UTC(year,month-1,day) - Date.UTC(1899,11,30))/86400000;
  }
  sheet.getRangeByIndexes(6,1,rows.length,rows[0].length).values = rows;
  sheet.getRange(`E7:E${6+rows.length}`).setNumberFormat("dd.mm.yyyy");
  book.recalculate();
  const key = await book.inspect({kind:"table",range:"Данные!B7:G9",include:"values,formulas",tableMaxRows:3,tableMaxCols:6});
  const errors = await book.inspect({kind:"match",searchTerm:"#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",options:{useRegex:true,maxResults:20}});
  console.log(prefix, key.ndjson, errors.ndjson);
  const staged = await SpreadsheetFile.exportXlsx(book);
  await staged.save(`staged-${prefix}.xlsx`);
  const after = await book.render({sheetName:"Данные",range:"B5:G14",scale:0.6,format:"png"});
  await fs.writeFile(`after-${prefix}.png`,new Uint8Array(await after.arrayBuffer()));
}
