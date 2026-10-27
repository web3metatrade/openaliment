'use strict';
for(const select of document.querySelectorAll('[data-sharing-items]')){
  const version=select.form?.elements.expected_version;
  if(version){const update=()=>{version.value=select.selectedOptions[0]?.dataset.grantVersion||'0';};select.addEventListener('change',update);update();}
}
