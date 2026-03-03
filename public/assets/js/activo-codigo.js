(function(){
  var el = document.getElementById('js-activo-codigo');
  if(!el) return;

  var sel = document.getElementById('tipo_activo_id');
  var codigo = document.getElementById('codigo_interno');
  var hint = document.getElementById('codigo_hint');

  var isEdit = el.dataset.isEdit === 'true';
  var baseUrl = el.dataset.baseUrl || '';

  function fetchCodigo(){
    if(!sel || !codigo) return;
    if(isEdit) return;

    var tipoId = parseInt(sel.value || '0', 10);
    if(!tipoId) return;

    if(codigo.value && codigo.value.trim() !== '') return;

    if(hint) hint.style.display = 'inline';

    var url = baseUrl + "/index.php?route=ajax_next_codigo_activo&tipo_activo_id=" + tipoId;
    fetch(url)
      .then(function(r){ return r.json(); })
      .then(function(j){
        if(j && j.ok && j.codigo){
          codigo.value = j.codigo;
        }
      })
      .catch(function(){});
  }

  if(sel){
    sel.addEventListener('change', function(){ fetchCodigo(); });
    fetchCodigo();
  }
})();
