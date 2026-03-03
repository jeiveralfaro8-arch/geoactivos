(function(){
  var el = document.getElementById('js-patron-cert');
  if(!el) return;

  var patronId = el.dataset.patronId || '';
  var baseUrl = el.dataset.baseUrl || '';

  var alertBox = document.getElementById('certAlert');
  function showErr(msg){
    if(!alertBox) return;
    alertBox.style.display = 'block';
    alertBox.textContent = msg || 'Error';
  }
  function clearErr(){
    if(!alertBox) return;
    alertBox.style.display = 'none';
    alertBox.textContent = '';
  }

  var btnUp = document.getElementById('btnCertSubir');
  var inp   = document.getElementById('inpCert');

  if(btnUp){
    btnUp.addEventListener('click', function(){
      clearErr();
      if(!inp || !inp.files || !inp.files.length){
        showErr('Selecciona un archivo.');
        return;
      }

      var f = inp.files[0];
      if(f.size > 10*1024*1024){
        showErr('El archivo supera 10MB.');
        return;
      }

      var fd = new FormData();
      fd.append('patron_id', patronId);
      fd.append('certificado', f);

      btnUp.disabled = true;
      btnUp.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';

      fetch(baseUrl + "/index.php?route=ajax_patron_cert_upload", {
        method: 'POST',
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(j){
        btnUp.disabled = false;
        btnUp.innerHTML = '<i class="fas fa-upload"></i> Subir archivo';

        if(j && j.ok){
          window.location.reload();
        }else{
          showErr((j && j.msg) ? j.msg : 'No se pudo subir el archivo.');
        }
      })
      .catch(function(){
        btnUp.disabled = false;
        btnUp.innerHTML = '<i class="fas fa-upload"></i> Subir archivo';
        showErr('Error de red al subir el archivo.');
      });
    });
  }

  var btnDel = document.getElementById('btnCertEliminar');
  if(btnDel){
    btnDel.addEventListener('click', function(){
      clearErr();
      if(!confirm('¿Eliminar el certificado cargado?')) return;

      var fd = new FormData();
      fd.append('patron_id', patronId);

      btnDel.disabled = true;
      btnDel.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

      fetch(baseUrl + "/index.php?route=ajax_patron_cert_delete", {
        method: 'POST',
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(j){
        btnDel.disabled = false;
        btnDel.innerHTML = '<i class="fas fa-trash"></i> Eliminar';

        if(j && j.ok){
          window.location.reload();
        }else{
          showErr((j && j.msg) ? j.msg : 'No se pudo eliminar.');
        }
      })
      .catch(function(){
        btnDel.disabled = false;
        btnDel.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
        showErr('Error de red al eliminar.');
      });
    });
  }
})();
