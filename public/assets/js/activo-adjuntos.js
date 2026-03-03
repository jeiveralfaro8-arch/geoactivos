(function(){
  var el = document.getElementById('js-activo-adj');
  if(!el) return;

  var baseUrl = el.dataset.baseUrl || '';

  var btn = document.getElementById('btnAdjSubirAct');
  var frm = document.getElementById('formAdjAct');
  var alertBox = document.getElementById('adjAlertAct');

  function showErr(msg){
    alertBox.style.display = 'block';
    alertBox.textContent = msg || 'Error al subir el archivo.';
  }
  function clearErr(){
    alertBox.style.display = 'none';
    alertBox.textContent = '';
  }

  if(btn && frm){
    btn.addEventListener('click', function(){
      clearErr();

      var file = document.getElementById('adjArchivoAct');
      if(!file || !file.files || !file.files.length){
        showErr('Selecciona un archivo.');
        return;
      }

      var fd = new FormData(frm);

      fetch(baseUrl + "/index.php?route=ajax_act_adj_upload", {
        method: "POST",
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if(j && j.ok){
          window.location.reload();
        }else{
          showErr((j && j.msg) ? j.msg : 'Error al subir el archivo.');
        }
      })
      .catch(function(){
        showErr('Error de red al subir el archivo.');
      });
    });
  }
})();

function delAdjAct(id){
  if(!confirm('¿Eliminar este adjunto?')) return;

  var el = document.getElementById('js-activo-adj');
  var baseUrl = el ? el.dataset.baseUrl || '' : '';

  var fd = new FormData();
  fd.append('id', id);

  fetch(baseUrl + "/index.php?route=ajax_act_adj_delete", {
    method: "POST",
    body: fd
  })
  .then(function(r){ return r.json(); })
  .then(function(j){
    if(j && j.ok){
      window.location.reload();
    }else{
      alert((j && j.msg) ? j.msg : 'No se pudo eliminar.');
    }
  })
  .catch(function(){
    alert('Error de red al eliminar.');
  });
}
