(function(){
  var cb  = document.getElementById('requiere_calibracion');
  var row = document.getElementById('row_periodicidad');
  var per = document.getElementById('periodicidad_meses');
  var bio = document.getElementById('es_biomedico');
  var fam = document.getElementById('familia');

  function sync(){
    if(!cb || !row || !per) return;

    if(cb.checked){
      row.style.display = '';
      if(bio) bio.checked = true;
    }else{
      row.style.display = 'none';
      per.value = '';
    }

    if(fam && fam.value === 'BIOMED' && bio){
      bio.checked = true;
    }
  }

  if(cb){ cb.addEventListener('change', sync); }
  if(fam){ fam.addEventListener('change', sync); }
  sync();
})();
