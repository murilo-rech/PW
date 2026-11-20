/**
 * admin.js — Interações comuns da área administrativa (Escola & Global)
 * EduSchedule
 */
import SessionStorage from "../../_common/scripts/storage.js";

const storage = new SessionStorage("admin");

/* ── Usuário logado na sidebar ── */
function renderUser() {
  const user = storage.getUser();
  if (!user?.name) return;
  document.querySelectorAll(".sidebar-user-name").forEach((element) => {
    element.textContent = user.name;
  });
  document.querySelectorAll(".sidebar-user-role").forEach((element) => {
    element.textContent = Number(user.type_id) === 1 ? "Administrador global" : "Administrador escolar";
  });
  document.querySelectorAll(".sidebar-user .avatar").forEach((avatar) => {
    avatar.textContent = user.name.split(" ").filter(Boolean).slice(0, 2).map((part) => part[0]).join("").toUpperCase();
  });
}

/* ── Logout: remove a sessão local antes de voltar ao login ── */
function bindLogout() {
  document.querySelectorAll("[data-logout]").forEach((link) => {
    link.addEventListener("click", () => storage.clearSession());
  });
}

/* ── Busca na tabela ── */
function initTableSearch() {
  const searchInput = document.querySelector('[data-table-search]');
  if (!searchInput) return;

  searchInput.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase().trim();
    document.querySelectorAll('[data-searchable-row]').forEach((row) => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  });
}

/* ── Configurações — abas ── */
function initSettingsTabs() {
  const links = document.querySelectorAll('.settings-link[data-settings-tab]');
  const panels = document.querySelectorAll('[data-settings-panel]');

  links.forEach((link) => {
    link.addEventListener('click', () => {
      links.forEach((l) => l.classList.remove('is-active'));
      panels.forEach((p) => p.classList.add('is-hidden'));
      link.classList.add('is-active');
      const target = link.getAttribute('data-settings-tab');
      const panel = document.querySelector(`[data-settings-panel="${target}"]`);
      if (panel) panel.classList.remove('is-hidden');
    });
  });
}

/* ── Gráficos animados ── */
function initCharts() {
  const bars = document.querySelectorAll('.chart-bar[data-height]');
  if (!bars.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const bar = entry.target;
      const height = bar.getAttribute('data-height');
      setTimeout(() => {
        bar.style.height = height;
        bar.style.transition = 'height 0.8s ease';
      }, 100);
      observer.unobserve(bar);
    });
  }, { threshold: 0.2 });

  bars.forEach((bar) => {
    bar.style.height = '0';
    observer.observe(bar);
  });
}

/* ── Exportar relatório (impressão em PDF pelo navegador) ── */
function initPrint() {
  document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });
}

/* ── Inicialização ── */
// Os CRUDs de usuários, escolas, planos e FAQ são inicializados pelos módulos
// de cada tela, que consomem os serviços da API.
renderUser();
bindLogout();
initTableSearch();
initSettingsTabs();
initCharts();
initPrint();
