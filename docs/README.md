# Documentația OpenAliment

## Pornire și contribuții

- [README al proiectului](../README.md)
- [Contribuții](../CONTRIBUTING.md) și [dezvoltare locală în engleză](development/README.md)
- [Licențe și drepturi](../LICENSING.md), [componente terțe](../THIRD_PARTY_NOTICES.md) și [securitate](../SECURITY.md)
- [Harta codului](architecture/README.md)
- [Operare cPanel, cron, backup și restaurare](operations/PRODUCTION_RUNBOOK.md)
- [Implementarea auditului și starea release-ului 0.6.0](implementation/PRODUCTION_READINESS_IMPLEMENTATION_2026-10-25.md)
- [Marketplace, tipărire serializată și referințe GS1 — 0.7.0](implementation/MARKETPLACE_GS1_0.7.0.md)

## Specificații și contracte active

- [Master OpenAliment](OPENALIMENT_MASTER.md)
- [API producători](API.md), [conectoare](INTEGRARI_PARTENERI.md), [Acasă și sincronizare](ACASA_SINCRONIZARE.md)
- [Ghidul complet API v2, chei și OAuth](api/DEVELOPER_GUIDE.md), [OpenAPI al platformei](../contracts/platform-openapi.json)
- [Portalul documentației API, Markdown și instrumente AI](api/README.md)
- [Open Food Facts: toate operațiile API și permisiunile](api/pages/surse-externe.md), cu [matricea acțiunilor verificată automat](api/external-action-coverage.json)
- [Auditul de acoperire API din 27 octombrie](audits/API_COMPLETENESS_2026-10-27.md), cu [rezultatele verificării](verification/api-completeness-2026-10-27.json)
- [Inventarul complet al acțiunilor și API-urilor candidate](api/API_ACTION_INVENTORY_2026-10-24.md), cu [catalog JSON](api/API_ACTION_INVENTORY_2026-10-24.json)
- [Nivelul resolverului GS1](GS1_IMPLEMENTATION.md) și [import/export](IMPORT_EXPORT.md)
- [Edițiile oficiale GS1 și păstrarea copiilor private](standards/GS1.md)
- [Marketplace și integrarea echipamentelor de tipărire](api/pages/marketplace.md)
- [Contracte machine-readable](../contracts/) și [migrări SQL](../database/migrations/)

## Operare

- [Instalare cPanel](DEPLOY_CPANEL.md)
- [SMTP](SMTP_CPANEL.md) și [mentenanță HTTPS](HTTPS_MAINTENANCE.md)
- [Dependențe și hashuri](DEPENDENCIES.json)
- [Operarea protecțiilor de securitate](security/SECURITY_OPERATIONS.md)
- [Soluțiile suplimentare de securitate, în ordinea implementării](security/SECURITY_IMPROVEMENT_PLAN_02_2026-10-24.md)

## Planuri și dovezi

- [Înregistrarea cu CUI și ANAF v9](plans/ANAF_REGISTRATION_PLAN_2026-10-28.md) — analiză și flux propus, cu [proba publică și cele 58 de câmpuri observate](verification/anaf-v9-probe-2026-10-28.json) și [macheta locală](ux/ANAF_REGISTER_CONCEPT_2026-10-28.html); implementarea și operarea sunt descrise în [ghidul ANAF](ANAF.md).
- [Planul detaliat pentru Open Food Facts](plans/OPEN_FOOD_FACTS_IMPLEMENTATION_PLAN_2026-10-27.md) — tabele, view-uri, API, operare și criterii de acceptare; [evaluarea strategiei](audits/OPEN_FOOD_FACTS_STRATEGY_REVIEW_2026-10-27.md). Implementarea 0.8.0 este descrisă în [ghidul de operare OFF](OPEN_FOOD_FACTS.md); activarea sursei este separată de instalare. [Raportul implementării](implementation/OPEN_FOOD_FACTS_0.8.0.md).
- [Auditul nucleului GS1 și corecțiile 0.7.1](audits/GS1_CORE_COMPATIBILITY_2026-10-25.md)

- [Auditul actual de implementare și pregătire pentru producție — 25.10.2026, release 0.5.5](audits/PRODUCTION_READINESS_AUDIT_2026-10-25.md)
- [Situația tuturor celor 28 de pachete, 56 de cerințe și 12 dependențe](audits/IMPLEMENTATION_COVERAGE_2026-10-25.md), cu [registru JSON](audits/production-readiness-2026-10-25.json)
- [Acoperirea celor 132 de scenarii noi, 49 OpenBread și 16 QR](audits/ACCEPTANCE_COVERAGE_2026-10-25.md) și [dovezile auditului](audits/evidence/2026-10-25/)
- [Planul integral](OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md), [backlog](plans/openaliment-backlog-2026-10-24.json) și [starea execuției](implementation/EXECUTION_2026-10-24.md)
- [Auditul structurii](STRUCTURE_AUDIT_2026-10-24.md) și [schimbările aplicate](implementation/STRUCTURE_CHANGES_2026-10-24.md)
- [Analiza securității și prioritățile de remediere](security/SECURITY_REVIEW_2026-10-24.md) — raport intern până la remedierea constatării SEC-01
- [Remedierile de securitate 0.5.3 și limitele verificate](implementation/SECURITY_HARDENING_0.5.3.md)
- [Analiza UI/UX pe pagini, încadrare și simetrie](ux/UI_UX_REVIEW_2026-10-24.md), cu [inventarul tuturor template-urilor și paginilor API](ux/UI_UX_PAGE_INVENTORY_2026-10-24.json)
- [Implementarea mecanismului API](implementation/API_PLATFORM_2026-10-24.md)
- [Activarea API pe producție](verification/API_ACTIVATION_2026-10-24.md)
- [Portalul documentației API și verificările sale](implementation/API_DOCUMENTATION_PORTAL_2026-10-24.md)
- [Dovezi istorice](verification/), [actualizări](updates/) și [istoric](history/)

Rapoartele noi ale testelor sunt generate în `tests/runtime/reports/`, nu peste dovezile unui release anterior. Arhiva OpenBread rămâne în locația sa originală, cu manifestele și avertizările de proveniență.

- [Referința API aprofundată și verificarea 0.5.2](implementation/API_REFERENCE_QUALITY_2026-10-24.md).
