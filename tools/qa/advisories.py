"""Query OSV for pinned versions only; no source, credentials or user data is sent."""
import argparse,json,re,urllib.error,urllib.request
from datetime import datetime,timezone
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
def inventory():
    data=json.loads((ROOT/'docs/DEPENDENCIES.json').read_text(encoding='utf-8'));packages=[];manual=[]
    for item in data['php_package_dependencies']:packages.append({'package':{'ecosystem':'Packagist','name':item['package']},'version':item['version']})
    for item in data['production_assets']:
        url=item['source'];match=re.search(r'(?:npm/|registry\.npmjs\.org/)((?:@[^/]+/)?[^/@]+)',url)
        if match:packages.append({'package':{'ecosystem':'npm','name':match[1]},'version':item['version']})
        else:manual.append({'file':item['file'],'reason':'Snapshot without a package version; pinned hash still requires upstream review.'})
    for line in (ROOT/'requirements-dev.txt').read_text(encoding='utf-8').splitlines():
        if re.fullmatch(r'[A-Za-z0-9_.-]+==[A-Za-z0-9_.+-]+',line):
            name,version=line.split('==');packages.append({'package':{'ecosystem':'PyPI','name':name},'version':version})
    lock=json.loads((ROOT/'package-lock.json').read_text(encoding='utf-8'))
    for path,item in lock.get('packages',{}).items():
        if path and item.get('version') and not item.get('link'):packages.append({'package':{'ecosystem':'npm','name':path.rsplit('node_modules/',1)[-1]},'version':item['version']})
    return list({json.dumps(p,sort_keys=True):p for p in packages}.values()),manual
def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--inventory-only',action='store_true');args=parser.parse_args();packages,manual=inventory()
    report={'checked_at':datetime.now(timezone.utc).isoformat(),'packages':packages,'manual_review':manual,'provider':'OSV','advisories':[],'state':'inventory_only'}
    if not args.inventory_only:
        request=urllib.request.Request('https://api.osv.dev/v1/querybatch',json.dumps({'queries':packages}).encode(),{'Content-Type':'application/json','User-Agent':'OpenAliment-Dependency-QA/0.5.3'},method='POST')
        try:
            with urllib.request.urlopen(request,timeout=30)as response:raw=response.read(4000001)
            if len(raw)>4000000:raise ValueError('Oversized advisory response')
            results=json.loads(raw)['results']
            if len(results)!=len(packages):raise ValueError('Incomplete advisory response')
            for package,result in zip(packages,results):
                if result.get('next_page_token'):raise ValueError('Advisory response needs pagination; review manually')
                for vuln in result.get('vulns',[]):report['advisories'].append({'package':package['package'],'version':package['version'],'id':vuln['id']})
            report['state']='completed'
        except (OSError,ValueError,KeyError):report['state']='unavailable'
    out=ROOT/'tests/runtime/reports';out.mkdir(parents=True,exist_ok=True);(out/'security-advisories.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps({'state':report['state'],'packages':len(packages),'advisories':report['advisories'],'manual_review':manual}));return 1 if report['state']=='unavailable' or report['advisories'] else 0
if __name__=='__main__':raise SystemExit(main())
