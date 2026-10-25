"""Focused source/secret checks. Reports locations and rule names, never matched values.

This complements security tests and a full SAST/secret-scanning service; it is not
an exhaustive scanner. Git mode checks tracked files, including forbidden paths.
"""
import argparse,json,os,re,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
SKIP={'.git','.tools','.venv','node_modules','storage','release','output','__pycache__'}
TEXT={'.php','.py','.js','.mjs','.cjs','.json','.md','.yml','.yaml','.txt','.ini','.env'}
def findings(relative,text):
    rules={
        'private-key':r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----[\s\S]{40,}',
        'openai-key':r'\bsk-(?:proj-)?[A-Za-z0-9_-]{35,}',
        'github-token':r'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})',
        'platform-token':r'\boa_(?:at_|rt_|print_)?[a-f0-9]{64}\b',
    }
    result=[]
    for rule,pattern in rules.items():
        for m in re.finditer(pattern,text):
            if rule=='platform-token' and len(set(m[0].rsplit('_',1)[-1]))<=2:continue # documented uniform placeholders
            result.append({'file':relative,'line':text.count('\n',0,m.start())+1,'rule':rule})
    if relative.endswith('.php') and relative.startswith(('app/','public/')) and not relative.startswith('app/Vendor/'):
        for m in re.finditer(r'(?<![>:\w])(?:eval|unserialize|shell_exec|passthru|system|exec|proc_open|popen)\s*\(',text):
            result.append({'file':relative,'line':text.count('\n',0,m.start())+1,'rule':'dangerous-php-function'})
    return result
def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--check',action='store_true');parser.parse_args()
    git=subprocess.run(['git','ls-files','-z'],cwd=ROOT,capture_output=True)
    mode='git-tracked' if git.returncode==0 else 'workspace-source'
    files=[ROOT/x for x in git.stdout.decode('utf-8').split('\0') if x] if git.returncode==0 else []
    if git.returncode!=0:
        for directory,children,names in os.walk(ROOT,followlinks=False):
            children[:]=[name for name in children if name not in SKIP and name!='runtime' and not (Path(directory)/name).is_symlink()]
            files.extend(Path(directory)/name for name in names if not name.endswith('.local.php') and name!='local.php')
    result=[];scanned=0
    for p in files:
        rel=p.relative_to(ROOT).as_posix()
        if mode=='git-tracked' and (rel.startswith('OpenBread_Codex_v1_2026-10-23/') or (rel.startswith('docs/') and p.suffix.lower() in {'.png','.jpg','.jpeg','.webp'})):
            result.append({'file':rel,'line':0,'rule':'private-evidence-tracked'});continue
        if rel=='config/local.php' or p.name.endswith('.local.php') or (p.name.startswith('.env') and p.name!='.env.example') or rel.startswith(('.tools/','storage/private/','tests/runtime/')):
            result.append({'file':rel,'line':0,'rule':'private-state-tracked'});continue
        if p.is_symlink() or not p.is_file() or p.suffix not in TEXT or p.stat().st_size>2100000:continue
        text=p.read_text(encoding='utf-8',errors='replace');result+=findings(rel,text);scanned+=1
    out=ROOT/'tests/runtime/reports';out.mkdir(parents=True,exist_ok=True)
    report={'mode':mode,'files_scanned':scanned,'findings':result,'matched_values_logged':False}
    (out/'security-sources.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps(report));return 1 if result else 0
if __name__=='__main__':raise SystemExit(main())
