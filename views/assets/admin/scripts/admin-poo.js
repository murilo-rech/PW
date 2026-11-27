import AuthGuard from "../../_common/scripts/auth-guard.js";
import SessionStorage from "../../_common/scripts/storage.js";
import Faqs from "../../_common/classes/Faqs.js";
import FaqCategories from "../../_common/classes/FaqCategories.js";
import Schools from "../../_common/classes/Schools.js";
import Plans from "../../_common/classes/Plans.js";
import { createFormController } from "../../_common/classes/FormController.js";

const session = new SessionStorage("admin");
const faqService = new Faqs();
const categoryService = new FaqCategories();
const schoolService = new Schools();
const planService = new Plans();
// Mesmo padrão do professor: cada serviço recebe o token da sessão.
[faqService, categoryService, schoolService, planService].forEach((client) => client.setAuthToken(session.getToken()));

function showError(error) {
  window.EduUI?.showToast(error, "error");
}

function initFaqCrud() {
  const list = document.querySelector("[data-faq-list]");
  const form = document.querySelector("[data-faq-form]");
  if (!list || !form) return;

  const formController = createFormController(form);
  const categoryList = document.querySelector("[data-category-list]");
  const categoryFilter = document.querySelector("[data-faq-category-filter]");
  const categorySelect = form.querySelector("[data-faq-category-select]");
  const categoryForm = document.querySelector("[data-category-form]");
  let faqs = [];
  let categories = [];
  let selectedCategory = "";

  function renderState(message) {
    const state = document.createElement("p");
    state.className = "faq-admin-a";
    state.textContent = message;
    list.replaceChildren(state);
  }

  function categoryName(id) {
    return categories.find((category) => category.id === id)?.name || "Categoria inativa";
  }

  function renderCategories() {
    const options = categories.map((category) => new Option(category.name, category.id));
    categorySelect.replaceChildren(new Option("Selecione a categoria", ""), ...options);
    categoryFilter.replaceChildren(
      new Option("Todas as categorias", ""),
      ...categories.map((category) => new Option(category.name, category.id))
    );
    categoryFilter.value = selectedCategory;

    if (!categoryList) return;
    if (!categories.length) {
      const empty = document.createElement("li");
      empty.className = "faq-admin-a";
      empty.textContent = "Nenhuma categoria cadastrada. Crie uma antes de adicionar perguntas.";
      categoryList.replaceChildren(empty);
      return;
    }
    categoryList.replaceChildren(...categories.map((category) => {
      const item = document.createElement("li");
      item.className = "chip";
      item.dataset.categoryId = category.id;
      const name = document.createElement("span");
      name.textContent = category.name;
      const edit = document.createElement("button");
      edit.type = "button";
      edit.className = "btn btn-ghost btn-sm";
      edit.textContent = "Editar";
      edit.addEventListener("click", () => fillCategoryForm(category));
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "btn btn-ghost btn-sm";
      remove.textContent = "Excluir";
      remove.addEventListener("click", () => removeCategory(category));
      item.append(name, edit, remove);
      return item;
    }));
  }

  function renderFaqs() {
    const visible = faqs.filter((faq) => !selectedCategory || faq.faqsCategoryId === Number(selectedCategory));
    if (!visible.length) {
      renderState(selectedCategory ? "Nenhuma pergunta nesta categoria." : "Nenhuma pergunta cadastrada.");
      return;
    }

    const cards = visible.map((faq) => {
      const index = faqs.indexOf(faq);
      const card = document.createElement("article");
      card.className = "faq-admin-card";
      card.dataset.faqId = faq.id;

      const content = document.createElement("section");
      content.className = "faq-admin-content";
      const category = document.createElement("span");
      category.className = "chip";
      category.textContent = categoryName(faq.faqsCategoryId);
      const question = document.createElement("h3");
      question.className = "faq-admin-q";
      question.textContent = faq.question;
      const answer = document.createElement("p");
      answer.className = "faq-admin-a";
      answer.textContent = faq.answer;
      content.append(category, question, answer);

      const actions = document.createElement("aside");
      actions.className = "faq-admin-actions";
      const moveUp = document.createElement("button");
      moveUp.className = "btn btn-ghost btn-sm";
      moveUp.type = "button";
      moveUp.textContent = "Subir";
      moveUp.disabled = index === 0 || Boolean(selectedCategory);
      moveUp.addEventListener("click", () => moveFaq(faq, -1));
      const moveDown = document.createElement("button");
      moveDown.className = "btn btn-ghost btn-sm";
      moveDown.type = "button";
      moveDown.textContent = "Descer";
      moveDown.disabled = index === faqs.length - 1 || Boolean(selectedCategory);
      moveDown.addEventListener("click", () => moveFaq(faq, 1));
      const statusButton = document.createElement("button");
      statusButton.className = `btn chip ${faq.isPublished ? "chip-green" : "chip-warning"}`;
      statusButton.type = "button";
      statusButton.textContent = faq.isPublished ? "Despublicar" : "Publicar";
      statusButton.addEventListener("click", () => changeFaqStatus(faq));

      const editButton = document.createElement("button");
      editButton.className = "btn btn-ghost btn-sm";
      editButton.type = "button";
      editButton.textContent = "Editar";
      editButton.addEventListener("click", () => fillFaqForm(faq));

      const deleteButton = document.createElement("button");
      deleteButton.className = "btn btn-danger btn-sm";
      deleteButton.type = "button";
      deleteButton.textContent = "Excluir";
      deleteButton.addEventListener("click", () => removeFaq(faq.id));
      actions.append(moveUp, moveDown, statusButton, editButton, deleteButton);

      card.append(content, actions);
      return card;
    });
    list.replaceChildren(...cards);
  }

  async function loadFaqs() {
    renderState("Carregando perguntas...");
    try {
      const [faqResponse, categoryResponse] = await Promise.all([
        faqService.listAllAdmin(),
        categoryService.listAll()
      ]);
      faqs = faqResponse.data || [];
      categories = categoryResponse.data || [];
      renderCategories();
      renderFaqs();
    } catch (error) {
      renderState("Não foi possível carregar as perguntas.");
      showError(error);
    }
  }

  function fillFaqForm(faq = null) {
    if (!categories.length) {
      window.EduUI?.showToast("Cadastre uma categoria antes de adicionar perguntas.", "warning");
      return;
    }
    formController.clear();
    form.querySelector('[name="id"]').value = faq?.id || "";
    categorySelect.value = faq?.faqsCategoryId ? String(faq.faqsCategoryId) : (selectedCategory || "");
    form.querySelector('[name="question"]').value = faq?.question || "";
    form.querySelector('[name="answer"]').value = faq?.answer || "";
    form.querySelector('[name="active"]').value = String(faq?.active ?? 1);
    form.querySelector('[name="sort_order"]').value = faq?.sortOrder ?? "";
    window.EduUI?.openModal("modal-faq");
  }

  async function changeFaqStatus(faq) {
    try {
      const response = await faqService.update(faq.id, { active: faq.isPublished ? 0 : 1 });
      window.EduUI?.showToast(response, "success");
      await loadFaqs();
    } catch (error) {
      showError(error);
    }
  }

  async function removeFaq(id) {
    if (!confirm("Tem certeza que deseja remover esta pergunta?")) return;
    try {
      const response = await faqService.remove(id);
      window.EduUI?.showToast(response.message, "warning");
      await loadFaqs();
    } catch (error) {
      showError(error);
    }
  }

  async function moveFaq(faq, direction) {
    const index = faqs.findIndex((item) => item.id === faq.id);
    const nextIndex = index + direction;
    if (index < 0 || nextIndex < 0 || nextIndex >= faqs.length) return;

    const reordered = [...faqs];
    [reordered[index], reordered[nextIndex]] = [reordered[nextIndex], reordered[index]];
    try {
      await Promise.all(reordered.map((item, position) => {
        const order = position + 1;
        return item.sortOrder === order ? Promise.resolve() : faqService.update(item.id, { sort_order: order });
      }));
      window.EduUI?.showToast("Ordem atualizada.", "success");
      await loadFaqs();
    } catch (error) {
      showError(error);
    }
  }

  function fillCategoryForm(category = null) {
    categoryForm.reset();
    categoryForm.querySelector('[name="id"]').value = category?.id || "";
    categoryForm.querySelector('[name="name"]').value = category?.name || "";
    window.EduUI?.openModal("modal-category");
  }

  async function removeCategory(category) {
    if (!confirm(`Excluir a categoria ${category.name}? As perguntas dela continuam cadastradas.`)) return;
    try {
      const response = await categoryService.remove(category.id);
      window.EduUI?.showToast(response.message, "warning");
      if (selectedCategory === String(category.id)) selectedCategory = "";
      await loadFaqs();
    } catch (error) {
      showError(error);
    }
  }

  document.querySelector("[data-add-faq]")?.addEventListener("click", () => fillFaqForm());
  document.querySelector("[data-add-category]")?.addEventListener("click", () => fillCategoryForm());
  categoryFilter?.addEventListener("change", (event) => {
    selectedCategory = event.target.value;
    renderFaqs();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const values = formController.read();
    formController.setLoading(true);
    try {
      const response = values.id
        ? await faqService.update(values.id, {
          faqs_category_id: values.faqs_category_id,
          question: values.question,
          answer: values.answer,
          active: values.active
        })
        : await faqService.insert(values);
      window.EduUI?.showToast(response, "success");
      window.EduUI?.closeModal("modal-faq");
      await loadFaqs();
    } catch (error) {
      showError(error);
    } finally {
      formController.setLoading(false);
    }
  });

  categoryForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const id = categoryForm.querySelector('[name="id"]').value;
    const name = categoryForm.querySelector('[name="name"]').value;
    try {
      const response = id
        ? await categoryService.update(id, { name })
        : await categoryService.insert({ name });
      window.EduUI?.showToast(response, "success");
      window.EduUI?.closeModal("modal-category");
      await loadFaqs();
    } catch (error) {
      showError(error);
    }
  });

  loadFaqs();
}

function initSchoolCrud() {
  const list = document.querySelector("[data-school-list]");
  const form = document.querySelector("[data-school-form]");
  const planSelect = form?.querySelector('[name="plan_id"]');
  if (!list || !form || !planSelect) return;

  const formController = createFormController(form);
  let schools = [];
  let plans = [];

  function renderState(message) {
    const state = document.createElement("p");
    state.className = "faq-admin-a";
    state.textContent = message;
    list.replaceChildren(state);
  }

  function renderSchools() {
    if (!schools.length) {
      renderState("Nenhuma escola cadastrada.");
      return;
    }

    const cards = schools.map((school) => {
      const card = document.createElement("article");
      card.className = "school-card";
      card.dataset.schoolId = school.id;
      card.dataset.searchableRow = "";

      const header = document.createElement("header");
      header.className = "school-header";
      const icon = document.createElement("span");
      icon.className = "school-icon";
      icon.textContent = "🏫";
      const details = document.createElement("section");
      details.className = "school-info";
      const name = document.createElement("h3");
      name.className = "school-name";
      name.textContent = school.name;
      const summary = document.createElement("span");
      summary.className = "school-code";
      const plan = plans.find((item) => item.id === school.planId);
      summary.textContent = `Código: ${school.code} | Plano: ${plan?.name || school.planId}`;
      details.append(name, summary);
      header.append(icon, details);

      const info = document.createElement("p");
      info.className = "school-code";
      info.textContent = `${school.email} · ${school.active === 1 ? "Ativa" : "Inativa"}`;

      const actions = document.createElement("footer");
      actions.className = "school-actions";
      const edit = document.createElement("button");
      edit.className = "btn btn-ghost btn-sm";
      edit.type = "button";
      edit.textContent = "Editar";
      edit.addEventListener("click", () => fillSchoolForm(school));
      const toggle = document.createElement("button");
      toggle.className = "btn btn-secondary btn-sm";
      toggle.type = "button";
      toggle.textContent = school.active === 1 ? "Inativar" : "Ativar";
      toggle.addEventListener("click", () => toggleSchool(school));
      const remove = document.createElement("button");
      remove.className = "btn btn-danger btn-sm";
      remove.type = "button";
      remove.textContent = "Excluir";
      remove.addEventListener("click", () => removeSchool(school.id));
      actions.append(edit, toggle, remove);
      card.append(header, info, actions);
      return card;
    });
    list.replaceChildren(...cards);
  }

  async function loadSchools() {
    renderState("Carregando escolas e planos...");
    try {
      const [schoolResponse, planResponse] = await Promise.all([
        schoolService.list(),
        planService.list()
      ]);
      schools = schoolResponse.data || [];
      plans = planResponse.data || [];
      renderSchools();
    } catch (error) {
      renderState("Não foi possível carregar escolas e planos.");
      showError(error);
    }
  }

  function fillSchoolForm(school = null) {
    formController.clear();
    const activePlans = plans.filter((plan) => plan.active === 1);
    const options = activePlans.map((plan) => {
      const option = new Option(`${plan.name} — R$ ${plan.price.toFixed(2)}`, plan.id);
      return option;
    });
    if (school && !plans.some((plan) => plan.id === school.planId && plan.active === 1)) {
      options.push(new Option(`Plano atual (${school.planId})`, school.planId));
    }
    if (!school && !activePlans.length) {
      const unavailable = new Option("Nenhum plano ativo disponível", "");
      unavailable.disabled = true;
      options.push(unavailable);
    }
    planSelect.replaceChildren(new Option("Selecione um plano", ""), ...options);
    form.querySelector('[name="id"]').value = school?.id || "";
    form.querySelector('[name="name"]').value = school?.name || "";
    form.querySelector('[name="code"]').value = school?.code || "";
    form.querySelector('[name="email"]').value = school?.email || "";
    planSelect.value = school?.planId || "";
    form.querySelector('[name="active"]').value = String(school?.active ?? 1);
    form.querySelector('[type="submit"]').disabled = !school && !activePlans.length;
    window.EduUI?.openModal("modal-school");
  }

  async function toggleSchool(school) {
    try {
      const response = await schoolService.update(school.id, {
        ...school.toPayload(),
        active: school.active === 1 ? 0 : 1
      });
      window.EduUI?.showToast(response, "success");
      await loadSchools();
    } catch (error) {
      showError(error);
    }
  }

  async function removeSchool(id) {
    if (!confirm("Tem certeza que deseja excluir esta escola?")) return;
    try {
      const response = await schoolService.remove(id);
      window.EduUI?.showToast(response.message, "warning");
      await loadSchools();
    } catch (error) {
      showError(error);
    }
  }

  document.querySelector("[data-add-school]")?.addEventListener("click", () => fillSchoolForm());

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const values = formController.read();
    formController.setLoading(true);
    try {
      const response = values.id
        ? await schoolService.update(values.id, values)
        : await schoolService.create(values);
      window.EduUI?.showToast(response, "success");
      window.EduUI?.closeModal("modal-school");
      await loadSchools();
    } catch (error) {
      showError(error);
    } finally {
      formController.setLoading(false);
    }
  });

  document.querySelector("[data-table-search]")?.addEventListener("input", (event) => {
    const term = event.target.value.trim().toLocaleLowerCase();
    list.querySelectorAll("[data-searchable-row]").forEach((card) => {
      card.hidden = !card.textContent.toLocaleLowerCase().includes(term);
    });
  });

  loadSchools();
}

if (new AuthGuard("../public/login.html", "admin", 1).protect()) {
  initFaqCrud();
  initSchoolCrud();
}
