import SessionStorage from "../../_common/scripts/storage.js";
import AuthGuard from "../../_common/scripts/auth-guard.js";
import Users from "../../_common/classes/Users.js";

const session = new SessionStorage("admin");
// Mesmo padrão do professor: cada serviço recebe o token da sessão (setAuthToken).
const api = (client) => {
  client.setAuthToken(session.getToken());
  return client;
};

function messageRow(tbody, text) {
  const row = document.createElement("tr");
  const cell = document.createElement("td");
  cell.colSpan = 5;
  cell.textContent = text;
  row.append(cell);
  tbody.replaceChildren(row);
}

async function start() {
  if (!new AuthGuard("../public/login.html", "admin", 2).protect()) return;
  const tbody = document.querySelector(".data-table tbody");
  const pending = document.querySelector("[data-pending-links]");
  if (!tbody || !pending) return;
  const users = api(new Users());

  async function approve(teacher, button) {
    button.disabled = true;
    try {
      const response = await users.updateById(teacher.id, { active: 1 });
      window.EduUI?.showToast(response.message || "Vínculo aprovado.", "success");
      await load();
    } catch (error) {
      button.disabled = false;
      window.EduUI?.showToast(error, "error");
    }
  }

  function renderPending(teachers) {
    if (!teachers.length) {
      const empty = document.createElement("li");
      empty.className = "request-card";
      empty.textContent = "Nenhuma solicitação de vínculo pendente.";
      pending.replaceChildren(empty);
      return;
    }

    pending.replaceChildren(...teachers.map((teacher) => {
      const item = document.createElement("li");
      item.className = "request-card";
      const info = document.createElement("article");
      const name = document.createElement("strong");
      name.textContent = teacher.name;
      const detail = document.createElement("p");
      detail.textContent = `${teacher.email}${teacher.specialization ? ` · ${teacher.specialization}` : ""}`;
      info.append(name, detail);
      const button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn-primary btn-sm";
      button.textContent = "Aprovar vínculo";
      button.addEventListener("click", () => approve(teacher, button));
      item.append(info, button);
      return item;
    }));
  }

  async function load() {
    try {
      const response = await users.list();
      const teachers = response.data.filter((user) => Number(user.typeId) === 3);
      renderPending(teachers.filter((teacher) => teacher.active !== 1));

      const linked = teachers.filter((teacher) => teacher.active === 1);
      if (!linked.length) {
        messageRow(tbody, "Nenhum professor com vínculo aprovado nesta escola.");
        return;
      }
      tbody.replaceChildren(...linked.map((teacher) => {
        const row = document.createElement("tr");
        for (const value of [teacher.name, teacher.email, teacher.specialization || "—", teacher.officeRoom || "—"]) {
          const cell = document.createElement("td");
          cell.textContent = value;
          row.append(cell);
        }
        const statusCell = document.createElement("td");
        const status = document.createElement("span");
        status.className = "chip chip-green";
        status.textContent = "Aprovado";
        statusCell.append(status);
        row.append(statusCell);
        return row;
      }));
    } catch (error) {
      messageRow(tbody, "Não foi possível carregar os professores associados.");
      pending.textContent = "Não foi possível carregar as solicitações.";
      window.EduUI?.showToast(error, "error");
    }
  }

  await load();
}

start();
