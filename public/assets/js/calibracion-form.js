function addPt(){
  var tb = document.querySelector('#tblPts tbody');
  if(!tb) return;
  var n = tb.querySelectorAll('tr').length + 1;

  var tr = document.createElement('tr');
  tr.innerHTML =
    '<td><input class="form-control" name="p_orden[]" type="number" value="'+n+'"></td>'+
    '<td><input class="form-control" name="p_magnitud[]" value=""></td>'+
    '<td><input class="form-control" name="p_unidad[]" value=""></td>'+
    '<td><input class="form-control" name="p_ref[]" type="number" step="0.0001" value=""></td>'+
    '<td><input class="form-control" name="p_eq[]" type="number" step="0.0001" value=""></td>'+
    '<td><input class="form-control" name="p_tol[]" type="number" step="0.0001" value=""></td>'+
    '<td class="text-right"><button type="button" class="btn btn-sm btn-outline-danger" onclick="rmPt(this)"><i class="fas fa-times"></i></button></td>';

  tb.appendChild(tr);
}
function rmPt(btn){
  var tr = btn && btn.closest ? btn.closest('tr') : null;
  if(tr) tr.parentNode.removeChild(tr);
}

function msgPatron(html, tipo){
  var el = document.getElementById('patronMsg');
  if(!el) return;
  el.style.display = 'block';
  el.className = 'alert alert-' + (tipo || 'info');
  el.innerHTML = html;
}

function setBtnLoading(on){
  var b = document.getElementById('btnCargarPts');
  if(!b) return;
  b.disabled = !!on;
  if(on){
    b.dataset._txt = b.innerHTML;
    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
  }else{
    if(b.dataset._txt) b.innerHTML = b.dataset._txt;
  }
}

function getSelectedPatronId(){
  var sel = document.getElementById('selPatrones');
  if(!sel) return 0;
  for (var i=0; i<sel.options.length; i++){
    if (sel.options[i].selected){
      return parseInt(sel.options[i].value, 10) || 0;
    }
  }
  return 0;
}

function escapeHtml(s){
  return String(s)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

function setPuntos(puntos){
  var tb = document.querySelector('#tblPts tbody');
  if(!tb) return;
  tb.innerHTML = '';

  if(!puntos || !puntos.length){
    addPt();
    return;
  }

  for (var i=0; i<puntos.length; i++){
    var pt = puntos[i] || {};
    var orden = (pt.orden != null ? pt.orden : (i+1));
    var magnitud = pt.magnitud != null ? String(pt.magnitud) : '';
    var unidad = pt.unidad != null ? String(pt.unidad) : '';
    var vr = pt.valor_referencia != null ? String(pt.valor_referencia) : '';
    var tol = pt.tolerancia != null ? String(pt.tolerancia) : '';

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><input class="form-control" name="p_orden[]" type="number" value="'+escapeHtml(orden)+'"></td>'+
      '<td><input class="form-control" name="p_magnitud[]" value="'+escapeHtml(magnitud)+'"></td>'+
      '<td><input class="form-control" name="p_unidad[]" value="'+escapeHtml(unidad)+'"></td>'+
      '<td><input class="form-control" name="p_ref[]" type="number" step="0.0001" value="'+escapeHtml(vr)+'"></td>'+
      '<td><input class="form-control" name="p_eq[]" type="number" step="0.0001" value=""></td>'+
      '<td><input class="form-control" name="p_tol[]" type="number" step="0.0001" value="'+escapeHtml(tol)+'"></td>'+
      '<td class="text-right"><button type="button" class="btn btn-sm btn-outline-danger" onclick="rmPt(this)"><i class="fas fa-times"></i></button></td>';
    tb.appendChild(tr);
  }
}

function cargarPuntosPatron(){
  var patronId = getSelectedPatronId();
  if(!patronId){
    msgPatron('<b>Selecciona un patrón</b> para cargar sus puntos.', 'warning');
    return;
  }

  setBtnLoading(true);
  msgPatron('Cargando puntos del patrón...','info');

  var url = AJAX_PATRON_PUNTOS_URL + '&patron_id=' + encodeURIComponent(patronId) + '&_=' + Date.now();

  fetch(url, { method:'GET', credentials:'same-origin', cache:'no-store', headers: { 'Accept':'application/json' } })
  .then(function(resp){
    return resp.text().then(function(txt){
      var data = null;
      try { data = JSON.parse(txt); } catch(e){ data = null; }
      return { ok: resp.ok, status: resp.status, text: txt, data: data };
    });
  })
  .then(function(r){
    setBtnLoading(false);

    if(!r.ok){
      var msg = (r.data && r.data.msg) ? r.data.msg : ('HTTP '+r.status+' (respuesta no válida)');
      msgPatron('<b>Error:</b> ' + escapeHtml(msg) + '<br><small>Endpoint: '+escapeHtml(AJAX_PATRON_PUNTOS_URL)+'</small>', 'danger');
      return;
    }

    if(!r.data || !r.data.ok){
      var msg2 = (r.data && r.data.msg) ? r.data.msg : 'Respuesta inválida (no JSON).';
      msgPatron('<b>Error:</b> ' + escapeHtml(msg2), 'danger');
      return;
    }

    setPuntos(r.data.puntos);

    var p = r.data.patron || {};
    var extra = [];
    if (p.marca) extra.push(p.marca);
    if (p.modelo) extra.push(p.modelo);
    if (p.serial) extra.push('S/N ' + p.serial);
    if (p.certificado_vigencia_hasta) extra.push('Vigencia: ' + p.certificado_vigencia_hasta);

    msgPatron('<b>Puntos cargados:</b> ' + (r.data.puntos ? r.data.puntos.length : 0) +
      '<br><small>Patrón: ' + escapeHtml(p.nombre || '') + (extra.length ? ' · ' + escapeHtml(extra.join(' · ')) : '') + '</small>',
      'success'
    );
  })
  .catch(function(err){
    setBtnLoading(false);
    msgPatron('<b>Error de red:</b> ' + escapeHtml(err && err.message ? err.message : String(err)), 'danger');
  });
}
