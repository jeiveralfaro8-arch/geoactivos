function openFotoAct(){
  var inp = document.getElementById('inpFotoAct');
  if(inp) inp.click();
}

(function(){
  var el = document.getElementById('js-activo-foto');
  if(!el) return;

  var activoId = el.dataset.activoId || '';
  var baseUrl = el.dataset.baseUrl || '';

  var inp = document.getElementById('inpFotoAct');
  var msg = document.getElementById('fotoActMsg');

  function setMsg(t){
    if(!msg) return;
    msg.style.display = 'block';
    msg.textContent = t || '';
  }

  if(inp){
    inp.addEventListener('change', function(){
      if(!inp.files || !inp.files.length) return;

      var f = inp.files[0];
      if(f.size > 8*1024*1024){
        setMsg('La imagen supera 8MB.');
        return;
      }

      setMsg('Subiendo foto...');

      var fd = new FormData();
      fd.append('activo_id', activoId);
      fd.append('foto', f);

      fetch(baseUrl + "/index.php?route=ajax_act_foto_upload", {
        method: 'POST',
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if(j && j.ok && j.path){
          setMsg('Foto actualizada.');
          var img = document.getElementById('imgActivoFoto');
          var ph  = document.getElementById('imgActivoFotoPh');
          var url = baseUrl + "/" + String(j.path).replace(/^\/+/, '');
          if(img){
            img.src = url + '?t=' + Date.now();
            img.style.display = 'block';
          }
          if(ph) ph.style.display = 'none';
          setTimeout(function(){ if(msg) msg.style.display='none'; }, 1500);
        }else{
          setMsg((j && j.msg) ? j.msg : 'No se pudo subir la foto.');
        }
      })
      .catch(function(){
        setMsg('Error de red al subir la foto.');
      });
    });
  }
})();

function delFotoAct(){
  if(!confirm('¿Eliminar la foto del activo?')) return;

  var el = document.getElementById('js-activo-foto');
  var activoId = el ? el.dataset.activoId || '' : '';
  var baseUrl = el ? el.dataset.baseUrl || '' : '';

  var msg = document.getElementById('fotoActMsg');
  function setMsg(t){
    if(!msg) return;
    msg.style.display = 'block';
    msg.textContent = t || '';
  }

  setMsg('Eliminando foto...');

  var fd = new FormData();
  fd.append('activo_id', activoId);

  fetch(baseUrl + "/index.php?route=ajax_act_foto_delete", {
    method: 'POST',
    body: fd
  })
  .then(function(r){ return r.json(); })
  .then(function(j){
    if(j && j.ok){
      setMsg('Foto eliminada.');

      var img = document.getElementById('imgActivoFoto');
      var ph  = document.getElementById('imgActivoFotoPh');

      if(img){
        img.src = '';
        img.style.display = 'none';
      }
      if(ph) ph.style.display = 'block';

      setTimeout(function(){ if(msg) msg.style.display='none'; }, 1500);
      setTimeout(function(){ window.location.reload(); }, 400);
    }else{
      setMsg((j && j.msg) ? j.msg : 'No se pudo eliminar la foto.');
    }
  })
  .catch(function(){
    setMsg('Error de red al eliminar la foto.');
  });
}
