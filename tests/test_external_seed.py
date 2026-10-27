import gzip,hashlib,importlib.util,json,tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[1];spec=importlib.util.spec_from_file_location('off_subset',root/'tools/data/prepare_off_subset.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.gtin('3274080005003')=='03274080005003' and m.gtin('1111111111111') is None
with tempfile.TemporaryDirectory(dir=root/'tests/runtime') as tmp:
    folder=Path(tmp);source=folder/'source.jsonl.gz'
    with gzip.open(source,'wt',encoding='utf-8') as f:
        f.write(json.dumps({'code':'3274080005003','product_type':'food','schema_version':1004,'product_name_ro':'Exemplu','countries_tags':['en:romania'],'private_field':'excluded'})+'\n')
    result=m.prepare(source,folder/'chunks',{'03274080005003'})
    assert len(result['chunks'])==1 and result['network_requests']==0
    entry=result['chunks'][0];data=(folder/'chunks'/entry['data']).read_bytes();manifest=json.loads((folder/'chunks'/entry['manifest']).read_text())
    assert manifest['sha256']==hashlib.sha256(data).hexdigest() and b'private_field' not in data
print(json.dumps({'passed':3,'failed':0}))
