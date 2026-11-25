/* Trillal / interfaz de juego: el servidor es la única autoridad de tiempo y puntuación. */
(() => {
  'use strict';
  const root = document.querySelector('[data-sala-id]');
  const toastEl = document.getElementById('juego-toast');
  let toastTimer;
  const reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  function toast(mensaje, error = false) {
    if (!toastEl) { if (error) alert(mensaje); return; }
    toastEl.textContent = mensaje;
    toastEl.classList.toggle('fallo', error);
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastEl.hidden = true; }, 5000);
  }
  async function pedir(url, opciones) {
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...opciones });
    const tipo = response.headers.get('content-type') || '';
    if (!tipo.includes('application/json')) throw new Error('La sesión puede haber caducado o PHP devolvió una página de error.');
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo completar la operación.');
    return data;
  }
  document.querySelectorAll('form[data-juego-ajax]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const boton = form.querySelector('button[type="submit"]');
      if (boton?.disabled) return;
      if (boton) { boton.disabled = true; boton.classList.add('procesando'); }
      try {
        const r = await pedir('jugar_api.php', { method: 'POST', body: new FormData(form) });
        if (r.mensaje) toast(r.mensaje);
        if (r.redirigir) window.location.assign(r.redirigir);
      } catch (err) { toast(err.message, true); }
      finally { if (boton) { boton.disabled = false; boton.classList.remove('procesando'); } }
    });
  });
  if (!root) return;
  const idSala = root.dataset.salaId;
  const token = root.dataset.juegoCsrf;
  const zona = document.getElementById('zona-juego');
  const tiempoEl = document.getElementById('reloj-valor');
  const relojEl = document.getElementById('reloj-partida');
  const etiqueta = document.getElementById('sala-etiqueta');
  const rankingEl = document.getElementById('ranking-listado');
  const rankingNumero = document.getElementById('ranking-numero');
  const miPuesto = document.getElementById('mi-puesto');
  const misPuntos = document.getElementById('mis-puntos');
  let datos = null;
  let desfaseMs = 0;
  let renderClave = '';
  let cargando = false;
  let bloqueado = false;
  let enviadoId = null;
  let confetiIniciado = false;
  let errorConsecutivo = 0;
  let premioRender = -1;
  const letras = ['A', 'B', 'C', 'D'];

  function nodo(tipo, clase, texto) {
    const el = document.createElement(tipo);
    if (clase) el.className = clase;
    if (texto !== undefined && texto !== null) el.textContent = String(texto);
    return el;
  }
  function agregar(parent, ...children) { children.forEach(child => parent.appendChild(child)); return parent; }
  function segundosRestantes(seg) { return Math.max(0, Math.ceil(seg)); }
  function formato(seg) {
    seg = segundosRestantes(seg);
    return `${String(Math.floor(seg / 60)).padStart(2, '0')}:${String(seg % 60).padStart(2, '0')}`;
  }
  function servidorMs() { return Date.now() + desfaseMs; }

  function actualizarReloj() {
    if (!datos) return;
    const esperando = datos.estado === 'programada';
    const terminado = datos.estado === 'finalizada' || datos.estado === 'cancelada';
    const destino = esperando ? datos.inicio * 1000 : datos.fin * 1000;
    const resta = terminado ? 0 : Math.max(0, (destino - servidorMs()) / 1000);
    tiempoEl.textContent = formato(resta);
    relojEl.classList.toggle('urgente', !esperando && !terminado && resta <= 10);
    relojEl.classList.toggle('critico', !esperando && !terminado && resta <= 5);
    const progreso = document.getElementById('barra-tiempo-pregunta');
    const indicador = document.getElementById('contador-pregunta');
    if (datos.estado === 'en_curso' && datos.pregunta) {
      const fin = datos.pregunta.fin_pregunta * 1000;
      const indice = datos.pregunta.indice;
      const inicia = datos.inicio * 1000 + Math.ceil(((indice - 1) * datos.duracion / datos.total_preguntas)) * 1000;
      const fraccion = Math.max(0, Math.min(1, (fin - servidorMs()) / Math.max(1000, fin - inicia)));
      if (progreso) {
        progreso.style.width = `${(fraccion * 100).toFixed(1)}%`;
        progreso.classList.toggle('agotandose', fraccion <= .22);
      }
      const restaPregunta = (fin - servidorMs()) / 1000;
      if (indicador) indicador.textContent = `${formato(restaPregunta)} para responder`;
      const siguiente = document.getElementById('btn-siguiente');
      if (siguiente) {
        const yaRespondio = !!datos.respuesta;
        const puedeAvanzar = restaPregunta <= 0;
        siguiente.hidden = !yaRespondio && !puedeAvanzar;
        if (puedeAvanzar) {
          siguiente.disabled = false;
          siguiente.textContent = 'Siguiente pregunta →';
          siguiente.classList.remove('espera');
          siguiente.classList.add('listo');
        } else {
          siguiente.disabled = true;
          siguiente.textContent = yaRespondio ? `Siguiente en ${formato(restaPregunta)}` : 'Responde para continuar';
          siguiente.classList.add('espera');
          siguiente.classList.remove('listo');
        }
      }
      if (servidorMs() >= fin + 450 && !cargando) consultarEstado();
    }
    if (!terminado && servidorMs() >= destino + 500 && !cargando) consultarEstado();
  }

  function mostrarRanking(data) {
    const jugadores = data.ranking || [];
    rankingNumero.textContent = `${jugadores.length} jugadores`;
    miPuesto.textContent = data.posicion ? `#${data.posicion}` : '—';
    misPuntos.textContent = `${data.puntos || 0} pts`;
    const diario = document.getElementById('ranking-diario');
    if (diario) {
      const publica = data.modalidad !== 'privada' && data.estado !== 'programada';
      diario.hidden = !publica;
      diario.textContent = !publica ? '' : (data.posicion_dia ? `♛ Puesto del día: #${data.posicion_dia}` : '♛ Ranking diario: esperando resultados');
    }
    rankingEl.replaceChildren();
    if (!jugadores.length) { rankingEl.appendChild(nodo('p', 'ranking-sin-datos', 'El podio se llenará cuando comience la partida.')); return; }
    jugadores.forEach((j, i) => {
      const fila = nodo('div', 'fila-ranking' + (j.id_usuario === data.usuario ? ' yo' : '') + (i < 3 ? ' top-' + (i + 1) : ''));
      agregar(fila, nodo('span', 'ranking-puesto', i === 0 ? '♛' : `#${i + 1}`),
        nodo('span', 'ranking-nombre', j.nombre + (j.id_usuario === data.usuario ? ' (tú)' : '')),
        nodo('strong', 'ranking-puntos', j.puntos + ' pts'));
      rankingEl.appendChild(fila);
    });
  }

  function renderEspera(data) {
    etiqueta.textContent = '◷ Esperando jugadores';
    zona.replaceChildren();
    const panel = nodo('div', 'sala-espera anim-entrada');
    const visual = nodo('div', 'sala-espera-anillos');
    agregar(visual, nodo('div', 'anillo anillo-1'), nodo('div', 'anillo anillo-2'), nodo('span', 'sala-espera-simbolo', '?!'));
    agregar(panel, nodo('span', 'juego-eyebrow', 'SALA DE ESPERA · TODO LISTO'), visual,
      nodo('h2', '', 'La partida está por comenzar'),
      nodo('p', '', 'Todos los jugadores responderán al mismo tiempo. El reloj oficial lo controla Trillal.'));
    const stats = nodo('div', 'espera-stats');
    agregar(stats,
      agregar(nodo('div', ''), nodo('strong', '', `${data.inscritos} / ${data.cupo}`), nodo('small', '', 'Participantes')),
      agregar(nodo('div', ''), nodo('strong', '', `${data.total_preguntas}`), nodo('small', '', 'Preguntas')));
    panel.appendChild(stats);
    panel.appendChild(nodo('p', 'espera-aviso', 'No cierres esta página. Tu partida aparecerá automáticamente.'));
    zona.appendChild(panel);
  }

  function imagenSegura(ruta, textoAlt) {
    if (!ruta || !/^uploads\/trivias\/[a-f0-9]{32}\.(png|jpg|webp)$/.test(ruta)) return null;
    const img = nodo('img', 'imagen-contenido');
    img.src = ruta; img.alt = textoAlt; img.loading = 'eager';
    return img;
  }
  function textoFeedback(correcta, puntos) {
    return correcta ? `¡ACERTASTE! +${puntos} puntos` : '¡CASI! LA SIGUIENTE ES TUYA';
  }
  function pintarRespuesta(datosRespuesta) {
    if (!datosRespuesta) return;
    bloqueado = true;
    zona.querySelectorAll('button.opcion-juego').forEach(btn => {
      btn.disabled = true;
      if (Number(btn.dataset.opcion) === Number(datosRespuesta.opcion)) {
        btn.classList.add('seleccionada', datosRespuesta.correcta ? 'acierto' : 'error-respuesta');
      } else btn.classList.add('deshabilitada');
    });
    const mensaje = zona.querySelector('#mensaje-respuesta');
    if (mensaje) {
      mensaje.textContent = textoFeedback(datosRespuesta.correcta, datosRespuesta.puntos);
      mensaje.className = 'feedback-respuesta visible ' + (datosRespuesta.correcta ? 'correcta' : 'incorrecta');
    }
    const siguiente = document.getElementById('btn-siguiente');
    if (siguiente) siguiente.hidden = false;
    if (datosRespuesta.correcta && !reduceMotion) destello();
  }
  function renderPregunta(data) {
    const p = data.pregunta;
    etiqueta.textContent = `● EN VIVO · PREGUNTA ${p.indice}/${data.total_preguntas}`;
    bloqueado = !!data.respuesta;
    zona.replaceChildren();
    const wrap = nodo('div', 'bloque-pregunta anim-entrada');
    const top = nodo('div', 'pregunta-arriba');
    agregar(top, nodo('span', 'juego-eyebrow', 'LA PRÓXIMA PREGUNTA PUEDE CAMBIARLO TODO'), nodo('span', 'contador-pregunta', '', ''));
    top.lastChild.id = 'contador-pregunta';
    const titulo = nodo('h2', 'titulo-pregunta', p.texto);
    const prog = nodo('div', 'barra-tiempo-externa');const bar=nodo('div', 'barra-tiempo-interna');bar.id='barra-tiempo-pregunta';prog.appendChild(bar);
    agregar(wrap, top, titulo);
    const pic = imagenSegura(p.imagen, 'Imagen de la pregunta');
    if(pic) agregar(wrap, agregar(nodo('div','pregunta-imagen'),pic));
    wrap.appendChild(prog);
    const opciones = nodo('div', 'rejilla-opciones');
    p.opciones.forEach((op,i)=>{
      const boton = nodo('button', 'opcion-juego');boton.type='button';boton.dataset.opcion=op.id_opcion;
      agregar(boton, nodo('span','letra-opcion',letras[i]||String(i+1)));
      const contenido=nodo('span','contenido-opcion');
      const imagen=imagenSegura(op.imagen_url,'Opción '+(letras[i]||i+1));
      if(imagen) contenido.appendChild(imagen);
      if(op.texto) contenido.appendChild(nodo('span','texto-opcion',op.texto));
      boton.appendChild(contenido);
      boton.addEventListener('click',()=>responder(p.id,Number(op.id_opcion),boton));
      opciones.appendChild(boton);
    });
    wrap.appendChild(opciones);
    const controles = nodo('div','controles-pregunta');
    const feedback = nodo('div', 'feedback-respuesta');feedback.id='mensaje-respuesta';feedback.setAttribute('aria-live','polite');
    const siguiente = nodo('button','juego-btn secundaria btn-siguiente espera','Siguiente pregunta →');
    siguiente.type='button';siguiente.id='btn-siguiente';siguiente.disabled=true;
    siguiente.addEventListener('click',()=>consultarEstado());
    controles.appendChild(feedback);
    controles.appendChild(siguiente);
    wrap.appendChild(controles);
    wrap.appendChild(nodo('p','ayuda-siguiente','Cuando termine el tiempo o hayas respondido, podrás avanzar a la siguiente pregunta desde aquí.'));
    zona.appendChild(wrap);
    if(data.respuesta) pintarRespuesta(data.respuesta);
    actualizarReloj();
  }
  async function responder(idPregunta,idOpcion,boton) {
    if (!datos || datos.estado !== 'en_curso' || bloqueado) return;
    bloqueado = true;
    boton.classList.add('enviando');
    zona.querySelectorAll('button.opcion-juego').forEach(b=>{b.disabled=true;});
    const body = new FormData();
    body.set('accion','responder');body.set('csrf_token',token);body.set('id_sala',idSala);
    body.set('id_pregunta',String(idPregunta));body.set('id_opcion',String(idOpcion));
    try {
      const r=await pedir('jugar_api.php',{method:'POST',body});
      enviadoId = idPregunta;
      const resultado={opcion:r.seleccion,correcta:r.correcta,puntos:r.puntos};
      if(datos.pregunta && datos.pregunta.id===idPregunta) {
        datos.respuesta=resultado;
        pintarRespuesta(resultado);
        if(resultado.correcta && !reduceMotion) boton.classList.add('salto-acierto');
      }
      setTimeout(consultarEstado,550);
    } catch(e) {bloqueado=false;toast(e.message,true);consultarEstado();}
    finally { boton.classList.remove('enviando'); }
  }
  function renderFinal(data) {
    etiqueta.textContent = '✓ PARTIDA FINALIZADA';
    zona.replaceChildren();
    const puesto=data.posicion||0;
    const bloque=nodo('div','pantalla-final anim-entrada');
    const cab=nodo('div','final-brillo');
    cab.textContent=puesto===1?'♛':puesto===2?'✦':puesto===3?'★':'?!';
    const distincion=puesto===1?'¡ERES EL CAMPEÓN!':puesto===2?'¡SEGUNDO LUGAR!':puesto===3?'¡SUBISTE AL PODIO!':'¡RETO COMPLETADO!';
    agregar(bloque, nodo('span','juego-eyebrow','TRILLAL / FIN DE LA PARTIDA'),cab,nodo('h2','titulo-final',distincion));
    const sub=nodo('p','final-subtitulo',puesto ? `Terminaste en la posición #${puesto}. ¡Cada respuesta cuenta!`:'La partida ha terminado.');bloque.appendChild(sub);
    const stats=nodo('div','final-datos');
    agregar(stats,
      agregar(nodo('div',''),nodo('small','','TU PUESTO'),nodo('strong','',puesto?`#${puesto}`:'—')),
      agregar(nodo('div',''),nodo('small','','PUNTUACIÓN'),nodo('strong','',`${data.puntos||0}`)));
    bloque.appendChild(stats);
    if(data.tipo_partida==='rapida') {
      bloque.appendChild(nodo('p','bono-rapida-final','⚡ Bono relámpago incluido: +10 puntos de puntuación por completar la partida.'));
    }
    const premio=data.premio||0;
    if(data.modalidad!=='privada' && data.posicion_dia){
      bloque.appendChild(nodo('p','posicion-dia',`♛ Tu puesto provisional en la clasificación diaria: #${data.posicion_dia}`));
    }
    if(premio>0) bloque.appendChild(nodo('p','premio-ganado',`♛ +${premio} puntos de Cultura conseguidos`));
    else if(data.modalidad!=='privada') bloque.appendChild(nodo('p','premio-pendiente','♛ Cultura pendiente de cierre: los primeros 3 del día recibirán puntos tras terminar la jornada (hora CDMX).'));
    else bloque.appendChild(nodo('p','premio-pendiente','Gracias por participar en esta sala privada. ¡Sigue practicando!'));
    const botones=nodo('div','final-acciones');
    const volver=nodo('a','juego-btn primaria','Volver a trivias ↗');volver.href='jugar.php';botones.appendChild(volver);
    const repetir=nodo('a','juego-btn secundaria','Ver esta trivia');repetir.href='trivia.php?id='+encodeURIComponent(data.id_trivia||root.dataset.trivia||'');
    // El enlace real se deduce desde la URL de retorno colocada por PHP.
    if(root.dataset.triviaId) repetir.href='trivia.php?id='+root.dataset.triviaId;
    botones.appendChild(repetir);
    bloque.appendChild(botones);
    zona.appendChild(bloque);
    premioRender=premio;
    if(!confetiIniciado){confetiIniciado=true;lanzarConfeti();}
  }
  function renderCancelada() {
    etiqueta.textContent = '× Sala cancelada';
    zona.replaceChildren();
    const tarjeta=nodo('div','juego-vacio');
    agregar(tarjeta,nodo('span','','◷'),nodo('h2','','Esta sala no llegó a empezar'),nodo('p','','No había participantes inscritos cuando llegó la hora de inicio.'));
    const a=nodo('a','juego-btn primaria','Explorar trivias →');a.href='jugar.php';tarjeta.appendChild(a);zona.appendChild(tarjeta);
  }
  function aplicar(data) {
    datos=data;
    mostrarRanking(data);
    const clave = data.estado==='en_curso' ? `pregunta-${data.pregunta?.id || 0}` : data.estado;
    if(clave!==renderClave || (data.estado==='finalizada' && data.premio!==premioRender)){
      renderClave=clave;
      if(data.estado==='programada') renderEspera(data);
      else if(data.estado==='en_curso' && data.pregunta){renderPregunta(data);}
      else if(data.estado==='finalizada') renderFinal(data);
      else if(data.estado==='cancelada') renderCancelada();
    } else if(data.estado==='en_curso' && data.respuesta && !bloqueado) pintarRespuesta(data.respuesta);
    actualizarReloj();
  }
  async function consultarEstado() {
    if (cargando) return;
    cargando=true;
    const inicio=Date.now();
    try {
      const r=await pedir('jugar_api.php?accion=estado&sala='+encodeURIComponent(idSala));
      const final=Date.now();
      // Compensa aproximadamente la latencia de ida y vuelta.
      desfaseMs = (r.servidor_ms || r.servidor*1000)-(inicio+final)/2;
      errorConsecutivo=0;
      aplicar(r);
    } catch(e) {
      errorConsecutivo++;
      if(errorConsecutivo===1 || errorConsecutivo%5===0) toast(e.message+' Reintentando conexión…',true);
    } finally {cargando=false;}
  }
  function destello() {
    const flash=nodo('div','destello-puntos');flash.textContent='✦';zona.appendChild(flash);
    setTimeout(()=>flash.remove(),700);
  }
  function lanzarConfeti() {
    if (reduceMotion) return;
    const canvas=document.getElementById('capa-confeti');if(!canvas)return;
    const ctx=canvas.getContext('2d');if(!ctx)return;
    const colores=['#efcf87','#fff0d1','#ad91ff','#ffffff','#dbb997','#f1a1b6'];
    const dpr=Math.min(window.devicePixelRatio||1,2);
    let ancho=0,alto=0;
    function ajustar(){ancho=window.innerWidth;alto=window.innerHeight;canvas.width=Math.round(ancho*dpr);canvas.height=Math.round(alto*dpr);canvas.style.width=ancho+'px';canvas.style.height=alto+'px';ctx.setTransform(dpr,0,0,dpr,0,0);}
    ajustar();
    const piezas=Array.from({length:Math.min(155,Math.max(70,Math.floor(ancho/8)))},()=>({
      x:Math.random()*ancho,y:-40-Math.random()*alto*.8,vx:(Math.random()-.5)*2.6,vy:2+Math.random()*4.3,
      w:3+Math.random()*6,h:5+Math.random()*9,ang:Math.random()*Math.PI*2,giro:(Math.random()-.5)*.14,
      color:colores[Math.floor(Math.random()*colores.length)]}));
    const inicio=performance.now();
    function cuadro(ahora){ctx.clearRect(0,0,ancho,alto);
      piezas.forEach(p=>{p.x+=p.vx+Math.sin(ahora/500+p.ang)*.35;p.y+=p.vy;p.ang+=p.giro;
        ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.ang);ctx.fillStyle=p.color;ctx.fillRect(-p.w/2,-p.h/2,p.w,p.h);ctx.restore();});
      if(ahora-inicio<4700)requestAnimationFrame(cuadro);else ctx.clearRect(0,0,ancho,alto);
    }
    requestAnimationFrame(cuadro);
  }
  consultarEstado();
  setInterval(consultarEstado,2300);
  setInterval(actualizarReloj,120);
})();
