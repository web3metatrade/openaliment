"""Portable delivery agent. Standard-library Python; never installed on shared hosting.

Default: validate/map remotely without importing. --apply explicitly imports.
Token is read only from OA_API_TOKEN. Events are immutable JSON files maintained by the ERP adapter.
"""
import argparse,hashlib,json,os,sys,time,urllib.error,urllib.parse,urllib.request

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--base-url',default='https://openaliment.ro')
    p.add_argument('--connector',required=True);p.add_argument('--event',required=True)
    p.add_argument('--apply',action='store_true');p.add_argument('--attempts',type=int,default=4)
    args=p.parse_args();base=urllib.parse.urlsplit(args.base_url)
    if base.scheme!='https' and not(base.scheme=='http' and base.hostname in ('localhost','127.0.0.1')):p.error('HTTPS is required.')
    if base.username or base.password or base.query or base.fragment or base.path not in ('','/'):p.error('Use an origin without credentials or a path.')
    if len(args.connector)!=32 or any(c not in '0123456789abcdef' for c in args.connector):p.error('Invalid connector ID.')
    token=os.environ.get('OA_API_TOKEN','')
    if not token: p.error('Set OA_API_TOKEN in the private runtime environment.')
    if not 1<=args.attempts<=6:p.error('Attempts must be 1–6.')
    with open(args.event,encoding='utf-8-sig') as f:event=json.load(f)
    body=json.dumps(event,ensure_ascii=False,separators=(',',':')).encode()
    if len(body)>1100000:p.error('Event exceeds request limit.')
    # Same immutable event => same key through retries and restarts.
    digest=hashlib.sha256(args.connector.encode()+b'|'+body).hexdigest()
    url=args.base_url.rstrip('/')+'/api/v2/manage/connectors/'+args.connector+('/ingest' if args.apply else '/preview')
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*_):return None
    opener=urllib.request.build_opener(NoRedirect)
    for attempt in range(args.attempts):
        request=urllib.request.Request(url,data=body,method='POST',headers={'Authorization':'Bearer '+token,'Content-Type':'application/json','Idempotency-Key':'agent-'+digest,'Accept':'application/json'})
        retry=None
        try:
            with opener.open(request,timeout=30) as response:result=json.load(response)
            # Receipt capability links are confidential; callers redirect stdout to a private file.
            print(json.dumps(result,ensure_ascii=False));return 0
        except urllib.error.HTTPError as e:
            if e.code!=429 and e.code<500:
                try:code=json.load(e).get('error_code','request_failed')
                except Exception:code='request_failed'
                print(json.dumps({'status':e.code,'error_code':code,'retry':False}),file=sys.stderr);return 1
            value=e.headers.get('Retry-After','')
            if value.isdecimal():retry=min(60,max(1,int(value)))
        except (urllib.error.URLError,TimeoutError):pass
        if attempt+1<args.attempts:time.sleep(retry or min(30,2**attempt))
    print('Delivery unresolved; keep the event and retry with identical content.',file=sys.stderr);return 2
if __name__=='__main__':sys.exit(main())
