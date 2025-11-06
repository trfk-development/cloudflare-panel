<?php
require_once 'header.php';
if (!isset($_SESSION['user_id'])) { header('Location: ' . BASE_PATH . 'login.php'); exit; }
include 'sidebar.php';
?>
<div class="content p-3">
  <h5><i class="fas fa-shield-alt me-2"></i>Security / Bots</h5>
  <div class="alert alert-info">Выберите домен на главной панели и используйте кнопки действий (молния/свиток). На этой странице доступны быстрые POST формы.</div>
  <form class="row g-2" onsubmit="return false;">
    <div class="col-auto">
      <input type="number" class="form-control" id="secDomainId" placeholder="ID домена">
    </div>
    <div class="col-auto">
      <button class="btn btn-dark" onclick="secToggle(true)"><i class="fas fa-bolt me-1"></i>Under Attack ON</button>
      <button class="btn btn-outline-dark" onclick="secToggle(false)"><i class="fas fa-bolt-slash me-1"></i>Under Attack OFF</button>
      <button class="btn btn-warning" onclick="botFight(true)"><i class="fas fa-robot me-1"></i>Bot Fight ON</button>
      <button class="btn btn-outline-warning" onclick="botFight(false)"><i class="fas fa-robot me-1"></i>Bot Fight OFF</button>
    </div>
  </form>
</div>
<script>
async function postSecurity(domainId, action){
  const form = new URLSearchParams();
  form.append('domain_id', String(domainId));
  form.append('action', action);
  const r = await fetch('security_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:form.toString()});
  const j = await r.json();
  alert(j.success? 'OK' : ('Ошибка: '+(j.error||'unknown')));
}
function secToggle(en){ const id=document.getElementById('secDomainId').value; if(!id) return; postSecurity(id,en?'under_attack_on':'under_attack_off'); }
function botFight(en){ const id=document.getElementById('secDomainId').value; if(!id) return; postSecurity(id,en?'bot_fight_on':'bot_fight_off'); }
</script>
<?php include 'footer.php'; ?> 