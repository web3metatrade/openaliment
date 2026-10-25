"""Preserve hash-pinned official GS1 PDFs privately; no catalog or original is rewritten."""
import argparse,hashlib,json,ssl,urllib.request
from pathlib import Path
from urllib.parse import urlsplit

ROOT=Path(__file__).resolve().parents[2]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None

def fetch(directory,only=None):
    catalog=json.loads((ROOT/'contracts/gs1/standards-catalog.json').read_text(encoding='utf-8'))
    directory=directory.resolve()
    if directory.is_relative_to(ROOT/'public'):raise ValueError('Reference PDFs must stay outside public/.')
    directory.mkdir(parents=True,exist_ok=True)
    opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPSHandler(context=ssl.create_default_context()))
    saved=[]
    for document in catalog['documents']:
        if only and document['id']!=only:continue
        url=urlsplit(document['pdf_url'])
        if url.scheme!='https' or url.hostname!='ref.gs1.org' or url.username or url.password or url.query or url.fragment:raise ValueError('Untrusted standards source')
        target=directory/document['filename']
        if target.is_symlink() or target.parent!=directory:raise ValueError('Unsafe standards path')
        if target.is_file() and target.stat().st_size==document['size_bytes'] and hashlib.sha256(target.read_bytes()).hexdigest()==document['sha256']:
            saved.append(document['id']);continue
        request=urllib.request.Request(document['pdf_url'],headers={'Accept':'application/pdf','User-Agent':'OpenAliment-Standards-Preservation/1.0'})
        with opener.open(request,timeout=45) as response:
            data=response.read(document['size_bytes']+1)
            if response.status!=200 or not data.startswith(b'%PDF-') or len(data)!=document['size_bytes'] or hashlib.sha256(data).hexdigest()!=document['sha256']:raise ValueError('Official bytes differ from the reviewed edition: '+document['id'])
        temporary=target.with_suffix('.pdf.tmp');temporary.write_bytes(data);temporary.chmod(0o600);temporary.replace(target);saved.append(document['id'])
    if only and not saved:raise ValueError('Unknown standard')
    return saved

if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--directory',type=Path,default=ROOT/'storage/private/standards/gs1');parser.add_argument('--only',choices=['uri-syntax','resolver','epc-compression','general-specifications']);args=parser.parse_args()
    print(json.dumps({'verified':fetch(args.directory,args.only),'public_distribution':False}))
