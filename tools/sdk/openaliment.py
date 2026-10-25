"""Small standard-library client. Never follows redirects or logs credentials."""
from __future__ import annotations
import base64
import hashlib
import json
import secrets
import urllib.error
import urllib.parse
import urllib.request

class ApiError(Exception):
    def __init__(self,status,code,message,request_id=None,retry_after=None):
        super().__init__(message)
        self.status,self.code,self.request_id,self.retry_after=status,code,request_id,retry_after

class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):
        return None

class OpenAliment:
    def __init__(self,base_url='https://openaliment.ro',token=None,*,timeout=15,allow_insecure_loopback=False):
        parsed=urllib.parse.urlsplit(base_url)
        local=allow_insecure_loopback and parsed.scheme=='http' and parsed.hostname in ['127.0.0.1','localhost','::1']
        if (parsed.scheme!='https' and not local) or parsed.username or parsed.password or parsed.query or parsed.fragment or parsed.path not in ['', '/']:
            raise ValueError('Use an HTTPS origin without path, query or credentials')
        self.base_url,self.token,self.timeout=base_url.rstrip('/'),token,timeout
        self._opener=urllib.request.build_opener(_NoRedirect())

    def request(self,method,path,data=None,*,idempotency_key=None):
        if not path.startswith('/') or path.startswith('//') or '\\' in path:
            raise ValueError('Use a relative absolute path on the configured origin')
        headers={'Accept':'application/json','User-Agent':'OpenAliment-Python/1.0'}
        if self.token:headers['Authorization']='Bearer '+self.token
        if idempotency_key:headers['Idempotency-Key']=idempotency_key
        payload=None
        if data is not None:payload=json.dumps(data,ensure_ascii=False,allow_nan=False).encode();headers['Content-Type']='application/json'
        req=urllib.request.Request(self.base_url+path,payload,headers,method=method.upper())
        try:response=self._opener.open(req,timeout=self.timeout)
        except urllib.error.HTTPError as error:response=error
        with response:
            raw=response.read(24_000_001)
            if len(raw)>24_000_000:raise ApiError(response.status,'response_too_large','Use streaming export for this volume')
            try:body=json.loads(raw) if raw else None
            except ValueError:body=None
            if not 200<=response.status<300:
                error=body if isinstance(body,dict) else {}
                raise ApiError(response.status,error.get('error_code',error.get('error','http_error')),error.get('message',error.get('error_description','HTTP request rejected')),error.get('request_id',response.headers.get('X-Request-ID')),response.headers.get('Retry-After'))
            return body if body is not None else raw

    def collection(self,path):
        cursor=None
        while True:
            query=path+('&' if '?' in path else '?')+urllib.parse.urlencode({'cursor':cursor}) if cursor else path
            page=self.request('GET',query)
            for item in page['items']:yield item
            cursor=page.get('next_cursor')
            if not cursor:return

    def food(self,code):
        return self.request('GET','/api/v2/public/food?'+urllib.parse.urlencode({'code':code}))

def pkce_parameters(client_id,redirect_uri,scope='home:read home:write'):
    """Store verifier/state privately until callback; do not log them or embed a static secret."""
    verifier=secrets.token_urlsafe(48)
    challenge=base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode()
    state=secrets.token_urlsafe(32)
    return {'client_id':client_id,'redirect_uri':redirect_uri,'response_type':'code','scope':scope,'state':state,'code_challenge':challenge,'code_challenge_method':'S256'},verifier,state
