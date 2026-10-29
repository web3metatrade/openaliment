"""Typed program contracts; private assessment keys are request/admin-only, never candidate DTOs."""
import copy,json
from common import ROOT

def definitions():
    def obj(p,required=None):return {'type':'object','properties':p,'required':list(p) if required is None else required,'additionalProperties':False}
    def s(n=2000):return {'type':'string','maxLength':n}
    def ref(n):return {'$ref':'#/components/schemas/Program'+n}
    def arr(item,maxi=1000):return {'type':'array','items':item,'maxItems':maxi}
    def null(v):return {'anyOf':[v,{'type':'null'}]}
    ID={'type':'string','pattern':'^[a-f0-9]{32}$'};B={'type':'boolean'};N={'type':'integer','minimum':0};rev=dict(N,description='Revizia citită înaintea modificării; 409 cere recitirea resursei.')
    track={'enum':['erp-integration','printing-integration','platform-operations','product-data']}
    # SQL UTC timestamps are intentionally represented as strings, not falsely labelled RFC3339.
    date=dict(s(32),description='Moment UTC în forma YYYY-MM-DD HH:mm:ss. Convertește explicit pentru afișare locală.')
    option=obj({'key':s(40),'text':s(2000)})
    answer={'anyOf':[s(500),arr(s(500),10),{'type':'object','maxProperties':10,'additionalProperties':s(500)}]}
    practice=obj({'question':s(2000),'options':arr(s(1000),6),'answer':{'type':'integer','minimum':0,'maximum':5},'explanation':s(3000)})
    lesson=obj({'id':ID,'title':s(190),'objective':s(2000),'body':s(20000),'resources':arr(obj({'title':s(190),'url':dict(s(2048),format='uri')}),10),'practice':practice})
    module=obj({'key':s(40),'title':s(190),'specialization':null(track),'lessons':arr(ref('Lesson'),20)})
    manifest=obj({'schema_version':{'const':'openaliment.course.v1'},'title':s(190),'version':s(40),'license':{'enum':['CC-BY-4.0','CC-BY-SA-4.0','CC0-1.0']},'summary':s(2000),'modules':arr(ref('Module'),30),'policy_version':{'const':'openaliment-partners-1.0'}})
    policy=obj({'blueprint':obj({k:{'const':v} for k,v in {'identity':[6,20],'uri':[6,20],'resolver':[6,20],'data':[4,15],'api':[4,15],'operations':[4,10]}.items()}),'minutes':{'const':45},'minimum_percent':{'const':85},'domain_minimum_percent':{'const':70},'all_critical_required':{'const':True},'retry_hours':{'const':24},'attempts_per_week':{'const':3},'validity_days':{'const':365}})
    bankq=obj({'key':s(100),'domain':{'enum':['identity','uri','resolver','data','api','operations']},'type':{'enum':['single','multiple','matching','uri']},'critical':B,'stem':s(4000),'options':arr(option,10),'answer':answer,'explanation':s(4000),'reference':dict(s(2048),format='uri')})
    bank=obj({'schema_version':{'const':'openaliment.assessment.v1'},'title':s(190),'version':s(40),'synthetic':B,'questions':dict(arr(ref('BankQuestion')),minItems=100),'policy':ref('AssessmentPolicy')},['title','version','questions'])
    contributor=obj({'name':s(120),'summary':s(1000),'categories':arr({'enum':['code','documentation','research','design','testing','translation','support','community']},8),'links':arr(dict(s(2048),format='uri'),5),'asset_id':null(ID)})
    presentation=obj({'description':s(4000),'services':arr(s(120),20),'regions':arr(s(120),20),'languages':arr(s(120),20),'website':null(dict(s(2048),format='uri')),'asset_id':null(ID)})
    contact=obj({'id':ID,'kind':{'enum':['email','phone']},'value':s(254),'revision':rev,'source':s(24),'verified':B,'public_consent':B})
    company=obj({'cui':s(10),'state':{'enum':['found','not_found']},'record':null({'$ref':'#/components/schemas/AnafRecord'}),'warnings':arr(s(2000)),'source':{'$ref':'#/components/schemas/AnafSource'}})
    credential=obj({'id':ID,'public_id':ID,'state':s(24),'specialization':track,'issuer':{'const':'OpenAliment'},'issued_at':date,'expires_at':date,'course_release_id':ID,'policy_version':s(40),'verification_url':dict(s(2048),format='uri'),'name':s(120)},['id','public_id','state','specialization','issuer','issued_at','expires_at','course_release_id','policy_version','verification_url'])
    auth=obj({'id':ID,'profile_id':ID,'specialization':track,'state':s(24),'issuer':{'const':'OpenAliment'},'policy_version':s(40),'issued_at':date,'expires_at':date,'gs1_accreditation':{'const':False},'company':dict(obj({'cui':s(10),'name':s(10000)}),description='Identitatea juridică a companiei autorizate, fără contacte sau rezultate personale.'),'verification_url':dict(s(2048),format='uri')})
    progress=obj({'enrollment_id':ID,'lesson_id':ID,'revision':rev,'completed':B,'practice_passed':B,'feedback':null(s(3000))})
    savedprogress=obj({'lesson_id':ID,'revision':rev,'completed':B,'practice_passed':B,'position_key':s(100)})
    labpayload={'type':'object','maxProperties':20,'description':'Scenariu virtual P01–P06 conform ghidului. Nu acceptă SQL, comenzi, URL-uri de callback sau execuție de cod. Max. 100 KB.','additionalProperties':True}
    schema={
      'Lesson':lesson,'Module':module,'Manifest':manifest,'PracticeDefinition':practice,'AssessmentPolicy':policy,'BankQuestion':bankq,'BankDocument':bank,
      'ContributorProfile':contributor,'PartnerPresentationData':presentation,'ContactData':contact,'CompanyObservation':company,
      'CredentialPublic':credential,'Authorization':auth,'SavedProgress':savedprogress,
      'Registration':obj({'display_name':dict(s(120),minLength=1),'email':dict(s(190),format='email'),'password':dict(s(200),minLength=12,writeOnly=True),'policy_accepted':{'const':True}}),
      'Token':obj({'token':{'type':'string','pattern':'^[a-f0-9]{64}$','writeOnly':True}}),
      'Confirmation':obj({'token':{'type':'string','pattern':'^[a-f0-9]{64}$','writeOnly':True},'accepted':{'const':True}}),
      'Verification':obj({'purpose':{'enum':['personal_email','partner_contact','contributor_consent','partner_invite']},'object_id':ID,'email':dict(s(190),format='email')},['purpose']),
      'Asset':obj({'purpose':{'enum':['avatar','logo','lab_evidence','lesson']},'content_base64':dict(s(2800000),minLength=1,writeOnly=True,description='PNG/JPEG/WebP original de cel mult 2 MiB și 4 megapixeli; serverul reencodează în PNG și elimină metadatele.'),'rights_confirmed':{'const':True}}),
      'Enrollment':obj({'release_id':ID,'specialization':track,'policy_accepted':{'const':True}}),
      'Progress':obj({'expected_revision':rev,'completed':B,'position_key':s(100)},['expected_revision','completed']),
      'Practice':obj({'expected_revision':rev,'answer':{'type':'integer','minimum':0,'maximum':5}},['expected_revision','answer']),
      'Attempt':obj({'enrollment_id':ID}),
      'Answer':obj({'expected_revision':rev,'answer':answer}),
      'LabCreate':obj({'enrollment_id':ID,'scenario':{'enum':['P01','P02','P03','P04','P05','P06']}}),
      'LabSave':obj({'expected_revision':rev,'payload':labpayload,'evidence_asset_id':null(ID)},['expected_revision','payload']),
      'Visibility':obj({'public_name':B}),
      'Contributor':obj({**contributor['properties'],'slug':dict(s(100),pattern='^[a-z0-9][a-z0-9-]{2,99}$'),'consent':B,'expected_revision':rev},['name','summary','categories']),
      'Consent':obj({'expected_revision':rev,'consent':{'const':True}}),
      'CompanyCheck':obj({'cui':dict(s(32),minLength=2,description='CUI românesc; prefixul RO este opțional. Nu trimite CNP.')}),
      'Application':obj({'company_check_id':ID,'cui':s(32),'company_confirmed':{'const':True},'previous_profile_id':null(ID)},['company_check_id','cui','company_confirmed']),
      'PartnerPresentation':obj({**presentation['properties'],'expected_revision':rev},['description','services','expected_revision']),
      'Contact':obj({'expected_revision':rev,'kind':{'enum':['email','phone']},'value':s(254),'public_consent':{'const':True}}),
      'Submission':obj({'expected_revision':rev,'policy_accepted':{'const':True}}),
      'Revision':obj({'expected_revision':rev}),
      'Reason':obj({'reason':dict(s(4000),minLength=1),'current_password':dict(s(200),writeOnly=True)},['reason']),
      'ContributorReview':obj({'expected_revision':rev,'action':{'enum':['approve','needs_changes','suspend']},'reason':dict(s(2000),minLength=1)}),
      'Course':obj({'manifest':ref('Manifest'),'slug':s(100),'expected_revision':rev},['manifest']),
      'CoursePublish':obj({'expected_revision':rev,'rights_confirmed':{'const':True}}),
      'Bank':obj({'bank':ref('BankDocument'),'expected_revision':rev},['bank']),
      'BankPublish':obj({'expected_revision':rev,'course_release_id':ID,'reviewed':{'const':True},'rights_confirmed':{'const':True}}),
      'LabReview':obj({**{k+'_points':{'type':'integer','minimum':0,'maximum':25} for k in ['identity','history','security','explanation']},'reason':dict(s(4000),minLength=1),'mandatory_checks':B,'physical_proof_reviewed':B},['identity_points','history_points','security_points','explanation_points','reason','mandatory_checks']),
      'PartnerReview':obj({'expected_revision':rev,'action':{'enum':['approve','needs_changes','reject','suspend','withdraw']},'reason':dict(s(4000),minLength=1),'representation_verified':B,'end_previous_affiliation':B,'accept_existing_company':B},['expected_revision','action','reason']),
      'PhoneReview':obj({'expected_revision':rev,'reason':dict(s(2000),minLength=1)}),
      'Decision':obj({'action':{'enum':['suspend','revoke','restore']},'reason':dict(s(2000),minLength=1)}),
      'ResultDecision':obj({'reason':dict(s(4000),minLength=1),'invalid_question_ids':arr(ID,30)},['reason']),
      'Accommodation':obj({'expected_revision':rev,'assessment_seconds':{'type':'integer','minimum':2700,'maximum':10800},'reason':dict(s(1000),minLength=1)}),
      'Staff':obj({'user_id':ID,'role':{'enum':['community_editor','course_editor','course_publisher','exam_reviewer','partner_reviewer']},'current_password':dict(s(200),writeOnly=True)},['user_id','role']),
      'Events':obj({'object_kind':{'enum':['contributor','partner_application','partner_authorization','credential','assessment_result','assessment_attempt','lab','enrollment','course','assessment_bank','assessment_release','staff']},'object_id':ID}),
      'Empty':obj({}),
      'Password':obj({'current_password':dict(s(200),writeOnly=True),'new_password':dict(s(200),minLength=12,writeOnly=True),'confirmation':dict(s(200),minLength=12,writeOnly=True)}),
      'CurrentPassword':obj({'current_password':dict(s(200),writeOnly=True)}),
      'SecurityUpdated':obj({'id':ID,'access_revoked':{'const':True},'csrf':dict(s(64),writeOnly=True,description='Token CSRF al sesiunii rotite; păstrează-l numai în clientul contului, fără logare sau publicare.')}),
      'PositionsImported':obj({'saved_positions':N,'credentials_accepted':{'const':0},'completion_accepted':{'const':False}}),
      'CreatedAccount':obj({'id':ID,'email_verified':B,'next':s(100)}),
      'VerificationSent':obj({'id':ID,'state':{'const':'sent'},'expires_in':{'const':1800}}),
      'VerificationPreview':obj({'purpose':s(40),'expires_at':date,'details':null({'oneOf':[ref('ContributorProfile'),obj({'company':s(10000)})]})}),
      'VerificationConfirmed':obj({'id':ID,'purpose':s(40),'state':{'const':'confirmed'},'next':dict(s(200),pattern='^/[^/].*')}),
      'Me':obj({'id':ID,'name':s(120),'email':dict(s(190),format='email'),'email_verified':B,'specializations':arr(track,4),'policy_version':s(40),'roles':arr(s(40),10),'is_admin':B}),
      'AssetMetadata':obj({'id':ID,'purpose':s(30),'state':s(24),'mime':{'const':'image/png'},'bytes':N,'width':N,'height':N,'url':s(200),'sha256':{'type':'string','pattern':'^[a-f0-9]{64}$'}}),
      'State':obj({'id':ID,'state':s(30)}),
      'CoursePublic':obj({'id':ID,'slug':s(100),'release_id':ID,'manifest':ref('Manifest'),'state':{'const':'published'}}),
      'CourseRelease':obj({'id':ID,'course_id':ID,'state':s(24),'manifest':ref('Manifest')}),
      'LessonPublic':obj({**lesson['properties'],'release_id':ID,'module':s(190)}),
      'LessonEnrolled':obj({**lesson['properties'],'module':s(190),'module_key':s(40)}),
      'CourseDraft':obj({'id':ID,'slug':s(100),'revision':rev,'state':s(24),'current_release_id':null(ID),'manifest':ref('Manifest')}),
      'EnrollmentSummary':obj({'id':ID,'release_id':ID,'specialization':track,'state':s(24),'revision':rev,'assessment_seconds':{'type':'integer','minimum':2700,'maximum':10800}}),
      'EnrollmentData':obj({'id':ID,'release_id':ID,'specialization':track,'state':s(24),'policy_version':s(40),'revision':rev,'assessment_seconds':{'type':'integer','minimum':2700,'maximum':10800},'theory_result_id':null(ID),'lessons':arr(ref('LessonEnrolled'),200),'progress':arr(ref('SavedProgress'),200)}),
      'ProgressSaved':progress,
      'AttemptData':obj({'id':ID,'enrollment_id':ID,'state':s(24),'due_at':date,'result_id':null(ID),'server_time':date,'items':arr(ref('AttemptItem'),30)}),
      'AttemptItem':obj({'id':ID,'position':N,'question':obj({'type':{'enum':['single','multiple','matching','uri']},'stem':s(4000),'options':arr(option,10)}),'revision':rev,'answer':null(answer)}),
      'AnswerSaved':obj({'id':ID,'revision':rev,'saved':{'const':True}}),
      'Result':obj({'id':ID,'attempt_id':ID,'enrollment_id':ID,'version':N,'score_basis_points':{'type':'integer','minimum':0,'maximum':10000},'passed':B,'critical_passed':B,'details':obj({'domains':obj({k:obj({'correct':N,'total':N}) for k in ['identity','uri','resolver','data','api','operations']}),'policy':ref('AssessmentPolicy'),'invalid_question_ids':arr(ID,30)},['domains','policy']),'appeal':null(obj({'reason':s(4000),'state':s(24),'at':date,'new_result_id':ID},['reason','state','at']))}),
      'Lab':obj({'id':ID,'enrollment_id':ID,'scenario':s(3),'revision':rev,'state':s(24),'payload':labpayload,'checks':arr(obj({'name':s(300),'passed':B}),30),'review':null(obj({'points':obj({k:{'type':'integer','minimum':0,'maximum':25} for k in ['identity','history','security','explanation']}),'passed':B,'mandatory_checks':B,'reason':s(4000)})),'evidence_asset_id':null(ID),'simulation':{'const':True}}),
      'CredentialPrivate':obj({**credential['properties'],'enrollment_id':ID,'result_id':ID},[x for x in credential['properties'] if x!='name']+['enrollment_id','result_id']),
      'ContributorPublic':obj({'id':ID,'slug':s(100),'profile':ref('ContributorProfile'),'state':{'const':'published'}}),
      'ContributorPrivate':obj({'id':ID,'slug':s(100),'profile':ref('ContributorProfile'),'state':s(24),'revision':rev,'submitted_revision':null(rev),'consented':B}),
      'CompanyCheckResult':obj({**company['properties'],'id':ID,'expires_at':date}),
      'PartnerApplication':obj({'id':ID,'tenant_id':ID,'profile_id':null(ID),'previous_profile_id':null(ID),'revision':rev,'state':s(24),'identity_verified':B,'presentation':ref('PartnerPresentationData'),'company':ref('CompanyObservation'),'contacts':arr(ref('ContactData'),10),'decision':null(obj({'action':s(30),'reason':s(4000),'at':date})),'requirements':arr(s(80),20)}),
      'PartnerPublic':obj({'id':ID,'slug':s(100),'name':s(10000),'cui':s(10),'country':{'const':'RO'},'description':s(4000),'services':arr(s(120),20),'regions':arr(s(120),20),'languages':arr(s(120),20),'website':null(s(2048)),'asset_id':null(ID),'contacts':arr(obj({'kind':s(20),'value':s(254),'source':s(24),'verified':{'const':True}}),10),'authorizations':arr(ref('Authorization'),100),'fiscal_checked_at':date}),
      'Affiliation':obj({'id':ID,'profile_id':ID,'user_id':ID,'role':s(24),'state':s(24),'started_at':date,'ended_at':null(date)}),
      'PartnerRefresh':obj({'id':ID,'requirements':arr(s(80),20),'company':ref('CompanyObservation')}),
      'BankSummary':obj({'id':ID,'title':s(190),'revision':rev,'current_release_id':null(ID)}),
      'BankDraft':obj({'id':ID,'revision':rev,'current_release_id':null(ID),'bank':ref('BankDocument')}),
      'BankPublished':obj({'id':ID,'bank_id':ID,'course_release_id':ID,'state':{'const':'published'},'question_count':{'type':'integer','minimum':100,'maximum':1000}}),
      'AccommodationSaved':obj({'id':ID,'revision':rev,'assessment_seconds':{'type':'integer','minimum':2700,'maximum':10800}}),
      'StaffGrant':obj({'id':ID,'user_id':ID,'role':s(40),'state':s(24)}),
      'Event':obj({'id':ID,'action':s(40),'at':date,'actor_id':null(ID),'details':{'type':'object','description':'Detalii de audit aprobate pentru rolul delegat; fără chei, tokenuri sau răspunsuri corecte.','additionalProperties':True}}),
      'Worker':{'oneOf':[obj({'state':{'enum':['not_installed','busy']}}),obj({'state':{'const':'completed'},'expired_attempts':N,'fiscal':s(24),'receipts_removed':N,'notification':s(24),'images_purged':N,'evidence_minimized':N})]},
    }
    person=obj({'id':ID,'name':s(120)})
    schema['EnrollmentData']['properties']['course_ref']=obj({'slug':s(100),'version':s(40),'sha256':dict(s(64),pattern='^[a-f0-9]{64}$')})
    schema['EnrollmentData']['required'].append('course_ref')
    for name in ['Result','Lab']:
        schema[name]['properties']['learner']=person
    schema['PartnerApplication']['properties'].update({'applicant':obj({'id':ID,'name':s(120),'email':dict(s(190),format='email'),'email_verified':B}),'existing_profile':null(obj({'id':ID,'cui':s(10),'name':s(190)}))})
    schema['BankEdition']=obj({'id':ID,'bank_id':ID,'course_release_id':ID,'version':s(40),'state':s(24),'sha256':dict(s(64),pattern='^[a-f0-9]{64}$'),'bank':ref('BankDocument')})
    schema['ContributorExport']=obj({'schema_version':{'const':'openaliment.contributor-export.v1'},'profile':ref('ContributorPrivate'),'history':arr(ref('Event'),500)})
    # Exported completion is informative; it cannot be applied to create a qualification.
    schema['LearnerExport']=obj({'schema_version':{'const':'openaliment.learner-export.v1'},'exported_at':date,'authoritative_for_authorization':{'const':False},'learner':ref('Me'),'enrollments':arr(obj({k:v for k,v in schema['EnrollmentData']['properties'].items() if k!='lessons'}),100),'results':arr(ref('Result'),500),'credentials':arr(ref('CredentialPrivate'),100)})
    schema['ImportPreview']=obj({'package':ref('LearnerExport')})
    schema['ImportChecked']=obj({'valid':{'const':True},'enrollment_count':N,'credentials_accepted':{'const':0},'completion_accepted':{'const':False},'note':s(2000)})
    schema['PartnerExport']=obj({'schema_version':{'const':'openaliment.partner-export.v1'},'exported_at':date,'application':ref('PartnerApplication'),'history':arr(obj({'id':ID,'action':s(40),'at':date,'details':{'type':'object','additionalProperties':True}}),500),'credentials_transferred':{'const':False},'producer_data_transferred':{'const':False}})
    schema['OAuthClient']={'$ref':'#/components/schemas/OAuthClient'}
    schema['Client']=obj({'id':ID,'name':s(120),'redirect_uris':arr(s(2048),10),'scopes':arr(s(40),20),'active':B,'created_at':date})
    schema['ClientCreated']=obj({'id':ID,'client_id':ID,'client_type':{'const':'public'},'token_endpoint_auth_method':{'const':'none'},'redirect_uris':arr(s(2048),10),'scopes':arr(s(40),20)})
    schema['Grant']=obj({'id':ID,'client_id':ID,'client_name':s(120),'scopes':arr(s(40),20),'expires_at':date,'state':s(24)})
    schema['AssetContent']=obj({**schema['AssetMetadata']['properties'],'content_base64':s(2800000)})
    schema['AdminEnrollment']=obj({**schema['EnrollmentSummary']['properties'],'user_id':ID})
    schema['ReviewItem']=obj({**schema['AttemptItem']['properties'],'marking':obj({'answer':answer,'explanation':s(4000),'reference':s(2048)})})
    schema['ReviewAttempt']=obj({**schema['AttemptData']['properties'],'items':arr(ref('ReviewItem'),30),'learner_id':ID,'private_review':{'const':True}})
    mapping={
      'public_contributors':'PageContributorPublic','public_contributors_get':'ContributorPublic','public_partners':'PagePartnerPublic','public_partners_get':'PartnerPublic','public_courses':'PageCoursePublic','public_courses_get':'CoursePublic','public_course_release':'CourseRelease','public_lesson':'LessonPublic','public_credential':'CredentialPublic','public_authorization':'Authorization',
      'register':'CreatedAccount','verification_preview':'VerificationPreview','verification_confirm':'VerificationConfirmed','verification_request':'VerificationSent','me':'Me','export':'LearnerExport','import_preview':'ImportChecked','asset_upload':'AssetMetadata','asset_get':'AssetMetadata','asset_withdraw':'State','enrollments':'PageEnrollmentSummary','enroll':'EnrollmentData','enrollment_get':'EnrollmentData','progress':'ProgressSaved','practice':'ProgressSaved','attempt_start':'AttemptData','attempt_get':'AttemptData','answer':'AnswerSaved','attempt_submit':'Result','results':'PageResult','result_get':'Result','appeal':'Result','labs':'PageLab','lab_create':'Lab','lab_get':'Lab','lab_save':'Lab','lab_submit':'Lab','credentials':'PageCredentialPrivate','credential_visibility':'CredentialPrivate',
      'contributors_own':'ItemsContributorPrivate','contributor_create':'ContributorPrivate','contributor_save':'ContributorPrivate','contributor_submit':'ContributorPrivate','contributor_withdraw':'State',
      'company_check':'CompanyCheckResult','company_check_get':'CompanyCheckResult','applications':'ItemsPartnerApplication','application_create':'PartnerApplication','application_get':'PartnerApplication','application_save':'PartnerApplication','contact_save':'PartnerApplication','application_submit':'PartnerApplication','application_withdraw':'PartnerApplication','application_appeal':'State','application_export':'PartnerExport','affiliations':'PageAffiliation','affiliation_end':'State','members':'PageAffiliation','partner_refresh':'PartnerRefresh',
      'admin_contributors':'PageContributorPrivate','admin_partners':'PagePartnerApplication','admin_labs':'PageLab','admin_courses':'PageCourseDraft','admin_banks':'PageBankSummary','admin_contributor_create':'ContributorPrivate','admin_contributor_save':'ContributorPrivate','admin_contributor_review':'ContributorPrivate',
      'admin_course_create':'CourseDraft','admin_course_get':'CourseDraft','admin_course_save':'CourseDraft','admin_course_publish':'CoursePublic','admin_course_retire':'State',
      'admin_bank_create':'BankDraft','admin_bank_get':'BankDraft','admin_bank_save':'BankDraft','admin_bank_publish':'BankPublished','admin_bank_retire':'State','admin_lab_get':'Lab','admin_lab_review':'Lab','admin_partner_get':'PartnerApplication','admin_partner_review':'PartnerApplication','admin_contact_verify':'State','admin_authorization':'Authorization','admin_results':'PageResult','admin_result_get':'Result','admin_result_decision':'Result','admin_accommodation':'AccommodationSaved','admin_credentials':'PageCredentialPrivate','admin_credential':'CredentialPrivate','admin_staff':'PageStaffGrant','admin_staff_grant':'StaffGrant','admin_staff_revoke':'State','admin_events':'PageEvent','admin_worker':'Worker',
    }
    mapping.update({'contributor_get':'ContributorPrivate','admin_contributor_get':'ContributorPrivate','admin_enrollments':'PageAdminEnrollment','admin_attempt_get':'ReviewAttempt','asset_content':'AssetContent','oauth_clients':'ItemsClient','oauth_client_create':'ClientCreated','oauth_client_revoke':'State','oauth_grants':'ItemsGrant','oauth_grant_revoke':'State'})
    mapping.update({'password_change':'SecurityUpdated','access_revoke':'SecurityUpdated','import_positions':'PositionsImported'})
    mapping.update({'contributor_export':'ContributorExport','admin_bank_release_get':'BankEdition'})
    for name in set(mapping.values()):
        if name.startswith('Page'):
            schema[name]=obj({'items':arr(ref(name[4:]),100),'next_cursor':null(ID),'has_more':B,'limit':{'type':'integer','minimum':1,'maximum':100}})
        elif name.startswith('Items'):schema[name]=obj({'items':arr(ref(name[5:]),200 if name=='ItemsGrant' else 100)})
    # Round-trip removes shared Python dict identities, so field-specific descriptions cannot bleed into other DTOs.
    return json.loads(json.dumps(schema)),mapping

def requests(obj,text,ID):
    schemas,_=definitions()
    return {'Program'+name:value for name,value in schemas.items()}

def install(add,fields,obj,arr,nullable,ref,S,B,N,ID,DATE):
    schemas,mapping=definitions()
    meanings={
      'objective':'Competența observabilă urmărită de lecție; se afișează înaintea conținutului.',
      'resources':'Referințe HTTPS selectate de editor; nu declanșează descărcări automate pe server.',
      'practice':'Exercițiu formativ public, distinct de banca privată a examenului.',
      'options':'Variante sau componente cu chei stabile; ordinea vizuală nu indică răspunsul corect.',
      'answer':'Răspunsul la exercițiu sau la întrebare, în formatul tipului declarat.',
      'explanation':'Justificarea soluției ori explicația candidatului; explicațiile băncii sunt private.',
      'key':'Cheie stabilă a modulului, întrebării sau variantei; nu este o credențială de acces.',
      'specialization':'Domeniul calificării: ERP, tipărire, operare sau date de produs; nu acordă drepturi asupra producătorilor.',
      'contributor_registry':'Registrul de contribuitori cu acord și aprobare, activ numai după migrarea programului.',
      'partner_program':'Programul OpenAliment de verificare și autorizare a firmelor; nu reprezintă acreditare GS1.',
      'learning_program':'Lecții publicate, progres personal, examene și probe practice cu evaluare independentă.',
      'specializations':'Specializările disponibile în program, fiecare cu probă practică proprie.',
      'lessons':'Lecțiile ediției, în ordinea modulului; pentru înscriere sunt incluse doar cele aplicabile specializării.',
      'license':'Licența explicită a materialelor proprii; nu schimbă drepturile asupra standardelor GS1 citate.',
      'summary':'Prezentarea scurtă a contribuției sau cursului, propusă de autor și verificată înainte de publicare.',
      'modules':'Modulele ediției cursului; un modul comun are specialization=null.',
      'policy_version':'Ediția politicii de formare și autorizare care se aplică acestei înregistrări.',
      'blueprint':'Distribuția fixă a întrebărilor și ponderilor: fiecare pereche este [număr, procent].',
      'identity':'Domeniul identității, mandatului și drepturilor asupra identificatorilor.',
      'uri':'Domeniul sintaxei, calificatorilor și codificării URI GS1 Digital Link.',
      'resolver':'Domeniul rezolvării identificatorilor, linksetului și profilului de conformitate.',
      'data':'Domeniul versiunilor, provenienței și informațiilor publicabile.',
      'api':'Domeniul permisiunilor API, idempotentei, reviziilor și integrării.',
      'operations':'Domeniul operării, tipăririi, continuității și incidentelor.',
      'minutes':'Durata standard de 45 de minute; adaptarea personală se stabilește separat înaintea tentativei.',
      'minimum_percent':'Pragul global ponderat de promovare; este verificat înainte de rotunjirea afișării.',
      'domain_minimum_percent':'Pragul minim cerut în fiecare domeniu, suplimentar pragului global.',
      'all_critical_required':'Toate întrebările critice extrase trebuie să fie corecte pentru promovare.',
      'retry_hours':'Intervalul minim dintre începuturile tentativelor, măsurat pe server.',
      'attempts_per_week':'Numărul maxim de tentative în fereastra mobilă de șapte zile.',
      'validity_days':'Valabilitatea standard a calificării, în zile de la emitere.',
      'domain':'Domeniul întrebării în blueprint; se păstrează în ediția privată.',
      'critical':'Indicator privat al întrebării critice; nu este inclus în răspunsul candidatului.',
      'stem':'Enunțul întrebării, afișat ca text fără executarea marcajelor HTML.',
      'reference':'Sursa HTTPS consultată pentru întrebarea și explicația revizuite.',
      'synthetic':'Material exclusiv de test; synthetic=true este refuzat în afara mediului test.',
      'questions':'Întrebările ediției private: minimum 100, cu chei unice și acoperirea blueprintului.',
      'policy':'Regulile imuabile de evaluare păstrate împreună cu ediția sau rezultatul.',
      'categories':'Tipurile de contribuții recunoscute: cod, documentație, cercetare, design, testare și alte categorii declarate.',
      'links':'Legăturile publice HTTPS propuse pentru contribuție, maximum cinci.',
      'asset_id':'ID-ul imaginii proprii încărcate în prealabil; null înseamnă fără imagine.',
      'services':'Serviciile declarate de companie și aprobate pentru prezentarea publică.',
      'regions':'Zonele în care compania declară că poate oferi serviciile.',
      'languages':'Limbile în care compania oferă serviciile; nu reprezintă rezultatul negocierii resolverului.',
      'website':'Adresa HTTPS publică a companiei; null dacă nu este propus un website.',
      'verified':'Dovada contactului este validă și neexpirată la momentul răspunsului.',
      'public_consent':'Acord explicit pentru publicarea acestui contact al companiei după verificare.',
      'cui':'Identificatorul fiscal al companiei consultate în ANAF; se păstrează ca șir.',
      'record':'Toate grupurile efectiv furnizate în observația ANAF v9; null pentru CUI negăsit.',
      'warnings':'Avertizări privind observația fiscală, fără aprobare implicită a reprezentării.',
      'public_id':'Identificatorul opac al verificării publice; nu dezvăluie identitatea persoanei fără acord.',
      'issuer':'Emitentul calificării sau autorizației: OpenAliment, distinct de GS1.',
      'profile_id':'Profilul juridic al companiei din registrul partenerilor, separat de contul persoanei.',
      'gs1_accreditation':'Întotdeauna false: programul nu pretinde acreditare acordată de GS1.',
      'enrollment_id':'Înscrierea personală în ediția și specializarea aplicabile.',
      'lesson_id':'Identificatorul stabil al lecției în ediția fixată la înscriere.',
      'completed':'Lecția este finalizată numai după rezolvarea exercițiului formativ.',
      'practice_passed':'Ultima verificare a exercițiului formativ a fost corectă.',
      'feedback':'Explicația exercițiului formativ; nu este cheia examenului.',
      'position_key':'Poziție de reluare a lecturii, fără efect asupra notei sau calificării.',
      'policy_accepted':'Acceptarea explicită a condițiilor programului pentru înscriere sau cerere.',
      'purpose':'Scopul verificării ori imaginii; drepturile și publicarea se verifică separat pentru fiecare scop.',
      'evidence_asset_id':'Imagine privată asociată probei practice; nu se publică în registrul partenerilor.',
      'public_name':'Alegerea persoanei privind afișarea numelui pe verificarea publică a calificării.',
      'slug':'Segment stabil al adresei publice, format din litere mici, cifre și liniuțe.',
      'company_check_id':'Ultima verificare ANAF activă a contului, valabilă 20 de minute și consumată la confirmare.',
      'company_confirmed':'Confirmarea explicită a companiei afișate după verificarea CUI-ului.',
      'previous_profile_id':'Profilul firmei anterioare, pentru un flux distinct de schimbare a companiei; nu îl rescrie.',
      'manifest':'Documentul cursului, cu module, lecții, exerciții, surse și licență; ediția publicată este imuabilă.',
      'bank':'Documentul privat al băncii de întrebări, accesibil numai editorilor și evaluatorilor autorizați.',
      'reviewed':'Confirmarea revizuirii întrebărilor, răspunsurilor, explicațiilor și acoperirii domeniilor.',
      'course_release_id':'Ediția imuabilă a cursului pe care se bazează banca ori calificarea.',
      'course_ref':'Identitatea portabilă a conținutului: slug, versiune și hash; permite reluarea lecturii între instalări cu aceeași ediție.',
      'identity_points':'Punctajul 0–25 pentru corectitudinea identificatorilor și semnificația datelor.',
      'history_points':'Punctajul 0–25 pentru păstrarea versiunilor, provenienței și recuperării.',
      'security_points':'Punctajul 0–25 pentru permisiuni, izolare și limitele publicării.',
      'explanation_points':'Punctajul 0–25 pentru explicația deciziilor și a probelor prezentate.',
      'mandatory_checks':'Evaluatorul confirmă toate condițiile obligatorii; punctajul singur nu le înlocuiește.',
      'physical_proof_reviewed':'Confirmarea revizuirii dovezii fizice pentru tipărire; un ACK sau o simulare nu sunt suficiente.',
      'representation_verified':'Evaluatorul a verificat mandatul persoanei; această dovadă nu provine automat din ANAF.',
      'accept_existing_company':'Confirmarea explicită a asocierii unui reprezentant verificat la firma existentă; nu transferă organizații, produse sau identificatori.',
      'applicant':'Identitatea contului solicitantului, vizibilă numai evaluatorului autorizat.',
      'existing_profile':'Profilul juridic deja existent pentru CUI, indicat numai evaluatorului; null dacă nu există.',
      'end_previous_affiliation':'Încheiere explicită a afilierii vechi după aprobarea firmei noi; fără transfer de produse sau GTIN-uri.',
      'invalid_question_ids':'ID-uri de itemi din tentativa curentă, anulate motivat la reevaluare și considerate corecte doar aici.',
      'assessment_seconds':'Durata personală, 2700–10800 de secunde, stabilită înaintea primei tentative.',
      'object_kind':'Categoria obiectului din audit; accesul este limitat de rolul delegat.',
      'email_verified':'Adresa contului a fost confirmată separat; nu verifică reprezentarea companiei.',
      'next':'Următoarea pagină internă permisă după crearea contului personal.',
      'roles':'Roluri delegate ale programului; nu acordă administrarea globală sau acces la alți producători.',
      'bytes':'Dimensiunea în octeți a imaginii reencodate, nu a unui executabil sau document arbitrar.',
      'width':'Lățimea imaginii reencodate, în pixeli.',
      'height':'Înălțimea imaginii reencodate, în pixeli.',
      'release_id':'Ediția imuabilă de conținut sau evaluare; contextul endpointului indică tipul.',
      'course_id':'Cursul părinte; reviziile lui publicate se identifică prin release_id.',
      'module':'Denumirea modulului care conține lecția.',
      'module_key':'Cheia stabilă a modulului în manifestul ediției.',
      'current_release_id':'Ultima ediție publicată a ciornei; null înaintea primei publicări.',
      'progress':'Progresul verificat al persoanei pentru lecțiile acestei înscrieri.',
      'due_at':'Termenul fix de predare calculat pe server; reluarea paginii nu îl prelungește.',
      'result_id':'Rezultatul curent al tentativei sau cel care susține calificarea.',
      'server_time':'Ora UTC a serverului, pentru afișarea duratei rămase fără încredere în ceasul dispozitivului.',
      'position':'Poziția întrebării în tentativa extrasă, pornind de la zero.',
      'question':'Enunțul și variantele servite candidatului, fără cheia privată de corectare.',
      'saved':'Confirmarea salvării răspunsului și a reviziei întoarse de server.',
      'attempt_id':'Tentativa de examen proprie sau examinată de un evaluator autorizat.',
      'score_basis_points':'Punctaj ponderat în sutimi de procent: 8500 înseamnă 85,00%; pragurile se verifică exact.',
      'passed':'Promovarea conform tuturor condițiilor aplicabile, nu doar mediei afișate.',
      'critical_passed':'Toate întrebările critice ale tentativei au fost corecte.',
      'domains':'Numărul de răspunsuri corecte și totalul extras în fiecare domeniu.',
      'correct':'Numărul răspunsurilor corecte în domeniu, fără publicarea cheii întrebărilor.',
      'total':'Numărul întrebărilor extrase pentru domeniul respectiv.',
      'appeal':'Contestația privată și soluționarea ei; null dacă nu există.',
      'at':'Momentul UTC al evenimentului sau deciziei.',
      'new_result_id':'Versiunea nouă a rezultatului, creată prin reevaluare fără ștergerea celei vechi.',
      'scenario':'Proba virtuală P01–P06; doar probele aplicabile specializării pot fi create.',
      'checks':'Verificările automate ale laboratorului, urmate de evaluarea independentă.',
      'review':'Rubrica, justificarea și rezultatul evaluării private; null înainte de review.',
      'points':'Cele patru componente de punctaj ale rubricii practice.',
      'security':'Criteriul securității din rubrică, evaluat cu 0–25 de puncte.',
      'submitted_revision':'Revizia profilului trimisă pentru publicare; null după o editare nouă.',
      'consented':'Persoana a confirmat acordul pentru exact revizia curentă, nu doar pentru o versiune anterioară.',
      'tenant_id':'Organizația asociată cererii de partener; existența ei nu acordă drepturi asupra identificatorilor.',
      'identity_verified':'Mandatul persoanei pentru companie a fost verificat separat de observația ANAF.',
      'presentation':'Descrierea, serviciile și imaginea propuse de companie; fără suprascrierea numelui legal sau CUI-ului.',
      'company':'Observația ANAF a firmei, cu sursa și avertizările fiscale.',
      'contacts':'Contactele companiei, cu proveniență, acord și stare de verificare separate.',
      'requirements':'Condițiile încă neîndeplinite pentru afișarea publică a partenerului.',
      'country':'Țara identității fiscale; fluxul ANAF disponibil aici este RO.',
      'authorizations':'Autorizațiile OpenAliment ale companiei, limitate la specializare și valabilitate.',
      'fiscal_checked_at':'Momentul ultimei observații fiscale; prospețimea se verifică și la citirea publică.',
      'started_at':'Momentul începerii afilierii sau tentativei, în funcție de resursă.',
      'ended_at':'Momentul încheierii afilierii; null cât timp ea nu a fost încheiată.',
      'bank_id':'Banca privată părinte a ediției de evaluare.',
      'question_count':'Numărul întrebărilor revizuite în ediția publicată a băncii private.',
      'user_id':'Contul persoanei vizate; afilierea nu acordă acces la rezultatele ei private.',
      'actor_id':'Contul care a înregistrat evenimentul, accesibil doar rolului autorizat pentru audit.',
      'expired_attempts':'Numărul tentativelor expirate închise în această tranșă limitată a workerului.',
      'fiscal':'Rezultatul reverificării fiscale din tranșă: nu era scadentă, actualizată sau indisponibilă.',
      'receipts_removed':'Numărul recipiselor de idempotentă expirate eliminate în tranșă; auditul deciziilor rămâne.',
      'evidence_minimized':'Tentative istorice ale căror răspunsuri au fost minimizate după perioada de păstrare, cu decizia și amprenta păstrate.',
      'authoritative_for_authorization':'Întotdeauna false: fișierul exportat nu poate crea singur o calificare sau autorizație.',
      'learner':'Datele contului care își exportă propriul progres, fără rezultatele colegilor.',
      'enrollments':'Înscrierile persoanei, cu edițiile și progresul păstrat.',
      'results':'Rezultatele proprii și versiunile lor; nu include banca sau răspunsurile corecte.',
      'credentials':'Calificările proprii, cu ID-uri publice și stările lor curente.',
      'enrollment_count':'Numărul înscrierilor recunoscute în exportul verificat.',
      'credentials_accepted':'Întotdeauna zero: importul informativ nu emite calificări.',
      'completion_accepted':'Întotdeauna false: importul nu substituie progresul verificat sau examenul.',
      'note':'Explicația efectului verificării de import și a limitelor ei.',
      'application':'Dosarul propriu al companiei, cu informația ANAF și stările programului.',
      'credentials_transferred':'Întotdeauna false: calificarea persoanei nu este transferată companiei prin export.',
      'producer_data_transferred':'Întotdeauna false: schimbarea firmei nu transferă produse, GTIN-uri sau documente.',
      'client_name':'Numele aplicației OAuth căreia persoana i-a acordat acces.',
      'marking':'Cheia, explicația și sursa întrebării, disponibile numai evaluatorului în sesiune autorizată.',
      'learner_id':'Contul cursantului din proba examinată, disponibil numai evaluatorului autorizat.',
      'theory_result_id':'Rezultatul teoretic valabil pentru aceeași ediție, reutilizabil între specializări; null dacă nu există.',
      'verification_url':'Adresa HTTPS de verificare a calificării sau autorizației. Este o adresă web a programului, nu un identificator GS1 inventat.',
      'private_review':'Întotdeauna true pentru DTO-ul evaluatorului; nu se folosește în interfețe publice.',
      'saved_positions':'Pozițiile de lectură importate în înscrieri proprii compatibile; nu modifică rezolvarea exercițiilor sau rezultatele.',
      'notification':'Rezultatul procesării unei notificări esențiale din coadă, fără expunerea mesajului sau adresei private.',
      'images_purged':'Numărul fișierelor de imagini retrase, eliminate după perioada de retenție; metadatele de audit se păstrează.',
      'role':'Rol limitat în program sau în afilierea companiei; nu reprezintă un drept global asupra producătorilor.',
    }
    for key,meaning in meanings.items():fields.setdefault(key,meaning)
    def describe(value):
        if isinstance(value,dict):
            for key,prop in value.get('properties',{}).items():
                if key in meanings:prop.setdefault('description',meanings[key])
            for child in value.values():describe(child)
        elif isinstance(value,list):
            for child in value:describe(child)
    for value in schemas.values():describe(value)
    schemas['BankQuestion']['properties']['answer']['description']='Cheia privată de corectare, accesibilă numai editorilor și evaluatorilor autorizați; nu se include în DTO-ul candidatului.'
    schemas['AttemptItem']['properties']['answer']['description']='Doar răspunsul salvat de candidat la propriul item; null înaintea primei salvări.'
    # Meaningful examples use synthetic identities; real assessment banks are never generated here.
    def example(value,stack=()):
        if value.get('properties',{}).get('schema_version',{}).get('const')=='openaliment.assessment.v1':
            questions=[]
            for domain in ['identity','uri','resolver','data','api','operations']:
                for i in range(17):
                    questions.append({'key':f'SYNTHETIC-{domain}-{i}','domain':domain,'type':'single','critical':i==0,'stem':f'Întrebare sintetică {domain} {i}; exclusiv test local.','options':[{'key':'a','text':'A'},{'key':'b','text':'B'}],'answer':'a','explanation':'Exemplu pentru testarea contractului.','reference':'https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/'})
            return {'schema_version':'openaliment.assessment.v1','title':'Bancă sintetică de contract','version':'test-1','synthetic':True,'questions':questions}
        if '$ref' in value:
            name=value['$ref'].rsplit('/',1)[1]
            if name.startswith('Program'):
                key=name[7:]
                return example(schemas[key],stack+(key,)) if len(stack)<12 else {}
            if name=='OAuthClient':return {'name':'Aplicație de exemplu','redirect_uris':['https://example.invalid/callback'],'scopes':['learning:read']}
            return {'date_generale':{'cui':900000001,'denumire':'Companie sintetică de exemplu'}} if name=='AnafRecord' else {'provider':'anaf','api_version':'v9','mapping_version':'openaliment.anaf.v1','endpoint':'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva','queried_for':'2026-10-29','fetched_at':'2026-10-29T10:00:00+00:00','sha256':'a'*64}
        if 'const' in value:return value['const']
        if 'enum' in value:return value['enum'][0]
        if 'anyOf' in value:return None if any(x.get('type')=='null' for x in value['anyOf']) else example(value['anyOf'][0],stack)
        if 'oneOf' in value:return example(value['oneOf'][0],stack)
        t=value.get('type')
        if t=='object':return {k:example(v,stack) for k,v in value.get('properties',{}).items() if k in value.get('required',[]) or k in ['title','name']}
        if t=='array':return [example(value['items'],stack) for _ in range(value.get('minItems',0))]
        if t=='boolean':return False
        if t in ['number','integer']:return value.get('minimum',0)
        if value.get('format')=='email':return 'partener@example.invalid'
        if value.get('format')=='uri':return 'https://example.invalid/referinta'
        if value.get('pattern')=='^[a-f0-9]{32}$':return '1'*32
        if value.get('pattern')=='^[a-f0-9]{64}$':return 'a'*64
        if value.get('pattern')=='^[a-z0-9][a-z0-9-]{2,99}$':return 'exemplu-sintetic'
        if value.get('pattern')=='^/[^/].*':return '/invatare/cont'
        if value.get('description','').startswith('Moment UTC'):return '2026-10-29 10:00:00'
        return 'Exemplu sintetic'[:value.get('maxLength',2000)]
    course_example=json.loads((ROOT/'contracts/program/learning-content.json').read_text(encoding='utf8'))
    course_example['modules']=course_example['modules'][:1]
    course_example['modules'][0]['lessons']=course_example['modules'][0]['lessons'][:1]
    course_example['title']='Curs de exemplu · servicii independente'
    for name,value in schemas.items():
        sample=example(value)
        if name=='Manifest':sample=copy.deepcopy(course_example)
        if name in ['Course','CourseDraft','CoursePublic','CourseRelease']:sample['manifest']=copy.deepcopy(course_example)
        add('Program'+name,value,sample)
    routes={r['handler']:r for r in json.loads((ROOT/'contracts/program/routes.json').read_text(encoding='utf8'))}
    def metadata(path,method,handler):
        if not handler.startswith('program.'):return None
        action=handler[8:];r=routes[handler]
        details='Datele sunt proiectate explicit pentru utilizatorul și rolul curent. Calificarea personală, autorizarea firmei și acreditarea GS1 sunt distincte. '
        if r['auth']=='public':details+='Se întorc numai datele aprobate, cu acord și condiții de valabilitate verificate la citire. Nu include răspunsuri corecte, dovezi private sau rezultate ale cursanților.'
        elif r['auth']=='program':details+='Acceptă sesiunea personală sau OAuth PKCE în contextul program, cu permisiunea '+r['scope']+'. Tokenurile ERP și permisiunile read/write ale producătorului nu acordă acest acces.'
        else:details+='Necesită sesiunea personală în browser; Bearer este refuzat. Cererile care modifică date cer X-CSRF-Token și aceeași origine. Rolurile delegate se verifică la fiecare cerere.'
        if r['body'] and action not in ['register','verification_preview','verification_confirm','verification_request','asset_upload','company_check','partner_refresh','admin_worker','password_change','access_revoke']:
            details+=' Folosește aceeași Idempotency-Key și același conținut la retry; revizia veche sau reutilizarea cheii pentru alt conținut primește 409.'
        if 'attempt' in action or action=='answer':details+=' Termenul se calculează pe server; reîncărcarea nu îl prelungește. Cheia de corectare nu este inclusă în DTO-ul candidatului. 409 indică stare incompatibilă, 429 limita tentativelor.'
        if 'bank' in action:details+=' Banca reală rămâne privată în baza de date și în copiile protejate. Nu se publică în repository, manifest, pachetul cPanel sau documentația publică. Edițiile publicate sunt imuabile.'
        if 'company' in action:details+=' Răspunsul include toate grupurile furnizate de ANAF v9 și proveniența. Confirmarea fiscală nu dovedește mandatul și nu acordă drepturi GS1.'
        if 'contact' in action:details+=' ANAF v9 nu furnizează email. Emailul se verifică separat; telefonul necesită dovadă înregistrată. Schimbarea valorii invalidează verificarea anterioară.'
        if 'import' in action:details+=' Importul este o verificare de portabilitate; nu reface un rezultat de examen și nu acordă autorizări.'
        return r['summary'].rstrip('.'),ref('Program'+mapping[action]),details
    return metadata
