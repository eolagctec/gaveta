// Arquivo app.js reestruturado (frontend)
// Carregado por index.html e retirar.php

let map = null;
let marker = null;
let dashboardIntervalId = null;

const $ = id => document.getElementById(id);
function show(el){ if(el) el.classList.remove('hidden'); }
function hide(el){ if(el) el.classList.add('hidden'); }
function setText(id, txt){ const e = $(id); if(e) e.textContent = txt; }

// Autenticação simples (apenas para demo)
function realizarLogin(){ const u = $('login_user')?.value || ''; const p = $('login_pass')?.value || ''; if(u==='admin' && p==='admin'){ localStorage.setItem('auth_token','true'); hide($('login-screen')); show($('main-dashboard')); initSystem(); } else { alert('Credenciais inválidas'); }}
function handleLogout(){ localStorage.removeItem('auth_token'); location.reload(); }

window.addEventListener('load', () => { if(localStorage.getItem('auth_token')==='true'){ hide($('login-screen')); show($('main-dashboard')); initSystem(); }});

function switchSection(sec){ document.querySelectorAll('.view-section').forEach(s=>s.classList.remove('active')); document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active')); const secEl=$('sec-'+sec); const navEl=$('nav-'+sec); if(secEl) secEl.classList.add('active'); if(navEl) navEl.classList.add('active'); if($('header-title')) $('header-title').textContent = sec.toUpperCase(); }

// Inicialização
function initSystem(){ try{ initMap(); }catch(e){ console.warn('Map init falhou',e);} syncDashboard(); carregarCondominios(); carregarEmpresas(); if(dashboardIntervalId) clearInterval(dashboardIntervalId); dashboardIntervalId = setInterval(syncDashboard,6000); }

// Mapa (Leaflet)
function initMap(){ if(typeof L === 'undefined') throw new Error('Leaflet nao carregado'); const defaultLatLng=[-25.4284,-49.2733]; map = L.map('map').setView(defaultLatLng,12); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map); marker = L.marker(defaultLatLng,{draggable:true}).addTo(map); marker.on('dragend', ()=>{ const ll = marker.getLatLng(); if($('condo_lat')) $('condo_lat').value = ll.lat.toFixed(6); if($('condo_lng')) $('condo_lng').value = ll.lng.toFixed(6); }); map.on('click', e=>{ if(marker) marker.setLatLng(e.latlng); if($('condo_lat')) $('condo_lat').value = e.latlng.lat.toFixed(6); if($('condo_lng')) $('condo_lng').value = e.latlng.lng.toFixed(6); }); }

function capturarGPS(){ if(!navigator.geolocation){ alert('Geolocalização não suportada'); return; } navigator.geolocation.getCurrentPosition(pos=>{ const lat=pos.coords.latitude; const lng=pos.coords.longitude; if($('condo_lat')) $('condo_lat').value = lat.toFixed(6); if($('condo_lng')) $('condo_lng').value = lng.toFixed(6); if(map){ map.setView([lat,lng],16); if(marker) marker.setLatLng([lat,lng]); } }, err=>{ console.warn(err); alert('Não foi possível obter localização'); }, {enableHighAccuracy:true, timeout:10000}); }

async function buscarCEP(cep){ if(!cep) return; const limpo = cep.replace(/\D/g,''); if(limpo.length!==8) return; try{ const res = await fetch(`https://viacep.com.br/ws/${limpo}/json/`); if(!res.ok) throw new Error('ViaCEP falhou'); const data = await res.json(); if(data.erro) { alert('CEP não encontrado'); return; } if($('condo_end')) $('condo_end').value = `${data.logradouro||''}${data.logradouro? ', ':''}${data.bairro||''} - ${data.localidade||''}/${data.uf||''}`; }catch(e){ console.error(e); alert('Erro ao consultar CEP'); } }

// DASHBOARD
async function syncDashboard(){ try{ const res = await fetch('api.php?action=dados_dashboard'); if(!res.ok) throw new Error('Falha dashboard'); const data = await res.json(); setText('dash-disp', data.disponivel ?? '-'); setText('dash-pend', data.pendente ?? '-'); setText('dash-exp', data.expirado ?? '-'); setText('dash-ret', data.retirado ?? '-'); }catch(e){ console.warn('syncDashboard',e); } }

// CONDOMINIOS
async function carregarCondominios(){ try{ const res = await fetch('api.php?action=listar_condominios'); if(!res.ok) throw new Error('Erro listar condominios'); const data = await res.json(); const select = $('select-condo-filtro'); if(select){ select.innerHTML = '<option value="">Escolha...</option>'; (data.condominios||[]).forEach(c=>{ const opt=document.createElement('option'); opt.value = c.id; opt.textContent = c.nome; select.appendChild(opt); }); }
    const tbody = $('tbl-condominios'); if(tbody){ tbody.innerHTML=''; (data.condominios||[]).forEach(c=>{ const tr=document.createElement('tr'); const tdNome=document.createElement('td'); tdNome.textContent = c.nome||'-'; tr.appendChild(tdNome); const tdCep=document.createElement('td'); tdCep.textContent = c.cep||'-'; tr.appendChild(tdCep); const tdCoords=document.createElement('td'); tdCoords.textContent = `${c.latitude||'-'}, ${c.longitude||'-'}`; tr.appendChild(tdCoords); const tdActions=document.createElement('td'); const btnQR=document.createElement('button'); btnQR.textContent='QR Code Portaria'; btnQR.className='btn btn-secondary btn-sm'; btnQR.addEventListener('click', ()=>exportarQR(c.id, c.nome)); tdActions.appendChild(btnQR); const btnDel=document.createElement('button'); btnDel.textContent='Excluir'; btnDel.className='btn btn-danger btn-sm'; btnDel.addEventListener('click', ()=>excluirCondo(c.id)); tdActions.appendChild(btnDel); tr.appendChild(tdActions); tbody.appendChild(tr); }); }
  }catch(e){ console.error('carregarCondominios',e); } }

async function saveCondo(e){ if(e && e.preventDefault) e.preventDefault(); const payload = { id: $('condo_id')?.value || '', nome: $('condo_nome')?.value || '', cep: $('condo_cep')?.value || '', endereco: $('condo_end')?.value || '', whatsapp_sindico: $('condo_wpp')?.value || '', prazo_retirada_horas: parseInt($('condo_prazo')?.value||24,10), latitude: $('condo_lat')?.value||'', longitude: $('condo_lng')?.value||'' }; try{ const res = await fetch('api.php?action=salvar_condominio',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) }); if(!res.ok) throw new Error('Erro salvar'); const form = $('form-condo'); if(form) form.reset(); await carregarCondominios(); alert('Condomínio salvo'); }catch(e){ console.error('saveCondo',e); alert('Erro ao salvar condomínio'); } }

async function excluirCondo(id){ if(!id) return; if(!confirm('Excluir condominio e dependencias?')) return; try{ const res = await fetch('api.php?action=excluir_condominio&id='+encodeURIComponent(id),{ method:'POST' }); if(!res.ok) throw new Error('Erro excluir'); await carregarCondominios(); }catch(e){ console.error('excluirCondo',e); alert('Erro ao excluir condomínio'); } }

// MORADORES
async function carregarMoradores(condoId){ const area = $('area-crud-moradores'); if(!condoId){ if(area) area.classList?.add('hidden'); return; } if(area) area.classList?.remove('hidden'); try{ const res = await fetch('api.php?action=listar_moradores&condominio_id='+encodeURIComponent(condoId)); if(!res.ok) throw new Error('Erro listar moradores'); const data = await res.json(); const tbody = $('tbl-moradores') || $('data-moradores'); if(tbody){ tbody.innerHTML=''; (data.moradores||[]).forEach(m=>{ const tr=document.createElement('tr'); ['numero','bloco','nome_morador','whatsapp_morador'].forEach(k=>{ const td=document.createElement('td'); td.textContent = m[k] ?? '-'; tr.appendChild(td); }); const tdQR=document.createElement('td'); const btnQR=document.createElement('button'); btnQR.className='btn btn-secondary btn-sm'; btnQR.textContent='Gerar / Imprimir QR'; btnQR.addEventListener('click', ()=>exportarQR(m.id, 'AP '+(m.numero||'') )); tdQR.appendChild(btnQR); tr.appendChild(tdQR); const tdA=document.createElement('td'); const edit=document.createElement('button'); edit.className='btn btn-primary btn-sm'; edit.textContent='Editar'; edit.addEventListener('click', ()=>editMorador(m)); tdA.appendChild(edit); const del=document.createElement('button'); del.className='btn btn-danger btn-sm'; del.textContent='Excluir'; del.addEventListener('click', ()=>excluirMorador(m.id, condoId)); tdA.appendChild(del); tr.appendChild(tdA); tbody.appendChild(tr); }); } }catch(e){ console.error('carregarMoradores',e); } }

function editMorador(m){ if(!m) return; $('morador_id').value = m.id||''; $('mor_num').value = m.numero||''; $('mor_bloco').value = m.bloco||''; $('mor_nome').value = m.nome_morador||''; $('mor_wpp').value = m.whatsapp_morador||''; $('morador_lat').value = m.latitude||''; $('morador_lng').value = m.longitude||''; $('morador-form-title') && ($('morador-form-title').textContent = 'Editar Cadastro Condominial'); }

async function saveMorador(e){ if(e && e.preventDefault) e.preventDefault(); const condoSelect = $('select-condo-filtro')?.value; if(!condoSelect){ alert('Selecione um condomínio'); return; } const payload = { condominio_id: condoSelect, id: $('morador_id')?.value||'', numero: $('mor_num')?.value||'', bloco: $('mor_bloco')?.value||'', nome: $('mor_nome')?.value||'', whatsapp: $('mor_wpp')?.value||'', latitude: $('morador_lat')?.value||'', longitude: $('morador_lng')?.value||'' };
  try{ const res = await fetch('api.php?action=salvar_morador',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) }); if(!res.ok) throw new Error('Erro salvar morador'); const form = $('form-morador'); if(form) form.reset(); await carregarMoradores(condoSelect); alert('Morador salvo'); }catch(e){ console.error('saveMorador',e); alert('Erro ao salvar morador'); } }

function resetMoradorForm(){ const form=$('form-morador'); if(form) form.reset(); $('morador_id').value=''; $('morador-form-title') && ($('morador-form-title').textContent='Cadastrar Unidade / Mobiliário'); }

async function excluirMorador(id, condoId){ if(!id) return; if(!confirm('Remover morador?')) return; try{ const res = await fetch('api.php?action=excluir_morador&id='+encodeURIComponent(id),{ method:'POST' }); if(!res.ok) throw new Error('Erro excluir morador'); await carregarMoradores(condoId); }catch(e){ console.error('excluirMorador',e); alert('Erro ao remover morador'); } }

// EMPRESAS
async function carregarEmpresas(){ try{ const res = await fetch('api.php?action=listar_empresas'); if(!res.ok) throw new Error('Erro listar empresas'); const data = await res.json(); const tbody = $('tbl-empresas'); if(!tbody) return; tbody.innerHTML=''; (data.empresas||[]).forEach(e=>{ const tr=document.createElement('tr'); const td=document.createElement('td'); td.textContent = e.nome||'-'; tr.appendChild(td); const tdA=document.createElement('td'); const del=document.createElement('button'); del.className='btn btn-danger btn-sm'; del.textContent='Excluir'; del.addEventListener('click', ()=>excluirEmpresa(e.id)); tdA.appendChild(del); tr.appendChild(tdA); tbody.appendChild(tr); }); }catch(e){ console.error('carregarEmpresas',e); } }

async function saveEmpresa(e){ if(e && e.preventDefault) e.preventDefault(); try{ const formData = new FormData(); formData.append('nome_empresa', $('emp_nome')?.value || ''); const logo = $('emp_logo')?.files?.[0]; if(logo) formData.append('logo_empresa', logo); const res = await fetch('api.php?action=salvar_empresa',{ method:'POST', body: formData }); if(!res.ok) throw new Error('Erro salvar empresa'); const form = $('form-empresa'); if(form) form.reset(); await carregarEmpresas(); alert('Empresa salva'); }catch(err){ console.error('saveEmpresa',err); alert('Erro ao salvar empresa'); } }

async function excluirEmpresa(id){ if(!id) return; if(!confirm('Remover frotista?')) return; try{ const res = await fetch('api.php?action=excluir_empresa&id='+encodeURIComponent(id),{ method:'POST' }); if(!res.ok) throw new Error('Erro excluir empresa'); await carregarEmpresas(); }catch(e){ console.error('excluirEmpresa',e); alert('Erro ao remover frotista'); } }

// QR
function exportarQR(id, nome){ const qrcodeBox = $('qrcode-box'); if(!qrcodeBox) return; qrcodeBox.innerHTML=''; const linkPublico = window.location.origin + '/gaveta/entregar.html?condominio_id=' + encodeURIComponent(id); if(typeof QRCode === 'undefined'){ alert('QRCode library missing'); return; } new QRCode(qrcodeBox,{ text: linkPublico, width:180, height:180 }); $('qr-modal-title') && ($('qr-modal-title').textContent = 'Acesso Frotas: ' + (nome||'')); $('qr-modal') && $('qr-modal').classList.remove('hidden'); }
function fecharModalQR(){ const m = $('qr-modal'); if(m) m.classList.add('hidden'); }

// GPS para formulário de moradores (retirar.php)
function getGPSLocation(){ if(navigator.geolocation){ navigator.geolocation.getCurrentPosition(pos=>{ if($('morador_lat')) $('morador_lat').value = pos.coords.latitude; if($('morador_lng')) $('morador_lng').value = pos.coords.longitude; }, ()=>{ alert('Erro ao obter localização via navegador'); }); } else { alert('Geolocalização não é suportada por este dispositivo.'); } }

// Bind global
window.realizarLogin = realizarLogin;
window.handleLogout = handleLogout;
window.switchSection = switchSection;
window.capturarGPS = capturarGPS;
window.buscarCEP = buscarCEP;
window.saveCondo = saveCondo;
window.carregarMoradores = carregarMoradores;
window.saveMorador = saveMorador;
window.saveEmpresa = saveEmpresa;
window.exportarQR = exportarQR;
window.fecharModalQR = fecharModalQR;
window.getGPSLocation = getGPSLocation;
window.editMorador = editMorador;
window.resetMoradorForm = resetMoradorForm;
