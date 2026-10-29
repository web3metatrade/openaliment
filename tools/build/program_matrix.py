"""Regenerate/check the UI/API action inventory from the actual program route registry."""
import argparse,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]

def document():
 routes=json.loads((ROOT/'contracts/program/routes.json').read_text('utf8'))
 lines=['# Matricea acțiunilor API · comunitate, parteneri și învățare','',f'{len(routes)} operații. Generată din `contracts/program/routes.json`; fiecare operație are contract și probă HTTP în `tests/test_program_http.py`.','','Regenerare: `python tools/build/program_matrix.py`. Verificare: aceeași comandă cu `--check`.','','| Acțiune / handler | Metodă și adresă | Acces și scope | Schema cererii |','| --- | --- | --- | --- |']
 for r in routes:
  lines.append(f"| {r['summary']} `{r['handler']}` | `{r['method']} {r['path']}` | `{r['auth']}` / `{r['scope'] or '—'}` | `{r['body'] or '—'}` |")
 return '\n'.join(lines)+'\n'

if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--check',action='store_true');a=p.parse_args()
 target=ROOT/'docs/api/PROGRAM_ACTION_MATRIX.md';expected=document()
 if a.check:
  if not target.exists() or target.read_text('utf8')!=expected:raise SystemExit('Program action matrix drift; regenerate.')
 else:target.write_text(expected,encoding='utf8')
 print(json.dumps({'passed':1,'failed':0,'program_actions':len(json.loads((ROOT/'contracts/program/routes.json').read_text('utf8')))}))
