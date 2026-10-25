// Optional independent oracle. Download the pinned official release before running.
// Usage: node tests/gs1-official-engine.mjs /absolute/path/to/extracted/engine
import {readFile,writeFile} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import {resolve,join} from 'node:path';
import {pathToFileURL} from 'node:url';
if(!process.argv[2])throw Error('Pass the local directory of the official GS1 engine. See tests/fixtures/gs1-engine-1.4.1.json.');
const provenance=JSON.parse(await readFile(new URL('./fixtures/gs1-engine-1.4.1.json',import.meta.url),'utf8'));
const directory=resolve(process.argv[2]);
for(const [name,expected]of Object.entries(provenance.files_sha256)){
  const bytes=await readFile(join(directory,name));if(createHash('sha256').update(bytes).digest('hex')!==expected)throw Error('Official engine hash differs: '+name);
}
const {GS1encoder}=await import(pathToFileURL(join(directory,'gs1encoder.mjs')).href),encoder=await GS1encoder.create();
const inputs=JSON.parse(await readFile(new URL('./runtime/gs1-generated-uris.json',import.meta.url),'utf8'));
const checks=inputs.map(c=>{try{encoder.dataStr=c.uri;return {name:c.name,passed:true};}catch(error){return {name:c.name,passed:false,error:error.message};}});
const report={source:'official GS1 Barcode Syntax Engine',version:provenance.version,artifact_digest:provenance.archive_sha256,scope:'URIs produced by actual PHP constructors; demonstration identifiers only; not resolver/POS certification',passed:checks.filter(c=>c.passed).length,failed:checks.filter(c=>!c.passed).length,checks};
await writeFile(new URL('./runtime/reports/gs1-official-engine.json',import.meta.url),JSON.stringify(report,null,2)+'\n');
console.log(JSON.stringify({passed:report.passed,failed:report.failed,failures:checks.filter(c=>!c.passed)}));process.exitCode=report.failed?1:0;
