"""Stream a local official OFF JSONL(.gz) export into bounded cPanel import chunks.

No network, DB writes, or uploading. Run outside the webroot; review the manifest.
"""
import argparse,gzip,hashlib,json,re
from pathlib import Path

def gtin(code):
    if not isinstance(code,str) or not re.fullmatch(r'(?:\d{8}|\d{12}|\d{13}|\d{14})',code):return None
    if sum(int(n)*(3 if i%2==0 else 1) for i,n in enumerate(code[-2::-1]))%10!=(-int(code[-1]))%10:return None
    return code.zfill(14)

def prepare(source,destination,selected,country=None,max_bytes=8388608):
    root=Path(__file__).resolve().parents[2];destination=destination.resolve()
    if destination.is_relative_to(root/'public'):raise ValueError('Output must stay outside public/.')
    destination.mkdir(parents=True,exist_ok=True)
    fields=re.search(r"const FIELDS = '([^']+)'",(root/'app/Domain/OpenFoodFacts.php').read_text()).group(1).split(',')
    files=[];handle=None;digest=None;count=size=0;rejected=0
    def finish():
        nonlocal handle
        if not handle:return
        handle.close();manifest={'schema_version':'openaliment.external-dataset.v1','provider':'openfoodfacts','license':'ODbL-1.0','source_environment':'production','sha256':digest.hexdigest(),'record_count':count,'source_url':'https://world.openfoodfacts.org/data'}
        filename=Path(handle.name).with_suffix('.manifest.json');filename.write_text(json.dumps(manifest,indent=2)+'\n');files.append({'data':Path(handle.name).name,'manifest':filename.name,'records':count});handle=None
    opener=gzip.open if source.suffix=='.gz' else open
    try:
        with opener(source,'rb') as stream:
            while True:
                line=stream.readline(16777217)
                if not line:break
                if len(line)>16777216:raise ValueError('Source line exceeds 16 MiB; preprocessing required.')
                try:product=json.loads(line)
                except (ValueError,UnicodeError):rejected+=1;continue
                if not isinstance(product,dict):rejected+=1;continue
                key=gtin(product.get('code'))
                if not key or (selected and key not in selected) or (country and country not in product.get('countries_tags',[])):continue
                if key=='09520000000011' or key[0]=='0' and key[1]=='2':continue
                if product.get('product_type','food')!='food' or product.get('schema_version',1000) not in range(1000,1005):rejected+=1;continue
                body=(json.dumps({k:v for k,v in product.items() if k in fields},ensure_ascii=False,separators=(',',':'))+'\n').encode()
                if len(body)>262144:rejected+=1;continue
                if handle and (size+len(body)>max_bytes or count>=10000):finish()
                if not handle:
                    handle=(destination/f'off-{len(files)+1:04}.ndjson').open('xb');digest=hashlib.sha256();count=size=0
                handle.write(body);digest.update(body);count+=1;size+=len(body)
        finish()
    finally:
        if handle:handle.close()
    return {'chunks':files,'rejected':rejected,'network_requests':0}

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('source',type=Path);p.add_argument('--output',required=True,type=Path);p.add_argument('--gtins',type=Path);p.add_argument('--country',help='OFF taxonomy tag, e.g. en:romania');args=p.parse_args()
    if not args.gtins and not args.country:p.error('Choose an explicit GTIN list or country subset.')
    selected=set()
    if args.gtins:
        for line in args.gtins.read_text().splitlines():
            code=gtin(line.strip())
            if not code:p.error('GTIN list contains an invalid identifier.')
            selected.add(code)
    print(json.dumps(prepare(args.source,args.output,selected,args.country)))
