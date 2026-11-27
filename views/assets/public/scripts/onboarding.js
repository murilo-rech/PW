import Schools from "../../_common/classes/Schools.js";
import Plans from "../../_common/classes/Plans.js";
import { createFormController } from "../../_common/classes/FormController.js";

const form = document.querySelector("#onboarding-form");
const message = document.querySelector("#onboarding-message");
const planList = document.querySelector("[data-onboarding-plans]");
const planInput = document.querySelector('[name="selected-plan"]');
const schools = new Schools();
const plansService = new Plans();
const money = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });
let plans = [];

function limitText(plan) {
    const students = Number(plan.maxStudents) === 0 ? "Alunos ilimitados" : `Até ${plan.maxStudents} alunos`;
    const teachers = Number(plan.maxTeachers) === 0 ? "professores ilimitados" : `${plan.maxTeachers} professores`;
    return `${students} / ${teachers}`;
}

function selectPlan(planId) {
    planInput.value = String(planId);
    planList.querySelectorAll("[data-plan-card]").forEach((card) => {
        card.classList.toggle("is-selected", card.dataset.planCard === String(planId));
        card.setAttribute("aria-pressed", String(card.dataset.planCard === String(planId)));
    });
}

function renderPlans() {
    planList.replaceChildren();
    if (!plans.length) {
        const empty = document.createElement("p");
        empty.textContent = "Não há planos ativos disponíveis no momento.";
        empty.setAttribute("role", "status");
        planList.append(empty);
        return;
    }

    plans.forEach((plan) => {
        const card = document.createElement("article");
        card.className = "plan-card plan-card-option";
        card.dataset.planCard = String(plan.id);
        card.tabIndex = 0;
        card.setAttribute("role", "button");

        const info = document.createElement("section");
        const name = document.createElement("strong");
        name.textContent = plan.name;
        const limits = document.createElement("span");
        limits.textContent = limitText(plan);
        info.append(name, limits);

        const price = document.createElement("strong");
        price.textContent = `${money.format(plan.price)}/mês`;

        card.append(info, price);
        card.addEventListener("click", () => selectPlan(plan.id));
        card.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                selectPlan(plan.id);
            }
        });
        planList.append(card);
    });

    // Plano escolhido na página de planos (?plan=ID) ou o primeiro ativo.
    const requested = new URLSearchParams(window.location.search).get("plan");
    const initial = plans.find((plan) => String(plan.id) === requested) || plans[0];
    selectPlan(initial.id);
}

async function loadPlans() {
    try {
        plans = (await plansService.listActive()).data;
        renderPlans();
    } catch (error) {
        planList.replaceChildren();
        const failure = document.createElement("p");
        failure.textContent = error.message || "Não foi possível carregar os planos.";
        failure.setAttribute("role", "alert");
        planList.append(failure);
        window.EduUI?.showToast(error, "error");
    }
}

function updateSummary() {
    const plan = plans.find((item) => String(item.id) === planInput.value);
    const value = (name) => form.elements.namedItem(name)?.value.trim() || "(Não informado)";
    document.querySelector("#summary-plan").textContent = plan
        ? `${plan.name} — ${money.format(plan.price)}/mês`
        : "(Nenhum plano selecionado)";
    document.querySelector("#summary-school-name").textContent = value("school-name");
    document.querySelector("#summary-school-code").textContent = value("school-code");
    document.querySelector("#summary-admin-name").textContent = value("admin-name");
    document.querySelector("#summary-admin-email").textContent = value("admin-email");
}

if (form && message && planList && planInput) {
    const controller = createFormController(form);
    document.querySelector("#btn-pre-summary")?.addEventListener("click", updateSummary);

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const values = controller.read();
        message.textContent = "";
        if (!values["selected-plan"]) {
            message.textContent = "Selecione um plano para continuar.";
            window.EduUI?.showToast(message.textContent, "warning");
            return;
        }
        if (values["admin-password"] !== values["admin-password-confirm"]) {
            const error = new Error("As senhas informadas não conferem.");
            message.textContent = error.message;
            window.EduUI?.showToast(error, "warning");
            return;
        }

        controller.setLoading(true, "Criando escola...");
        try {
            const response = await schools.register({
                plan_id: values["selected-plan"],
                school_name: values["school-name"],
                school_code: values["school-code"],
                school_cnpj: values["school-cnpj"],
                school_phone: values["school-phone"],
                admin_name: values["admin-name"],
                admin_email: values["admin-email"],
                admin_password: values["admin-password"],
                admin_password_confirm: values["admin-password-confirm"]
            });

            message.textContent = `${response.message} Faça login com as credenciais do administrador.`;
            window.EduUI?.showToast(response, "success");
            form.reset();

            const loginUrl = new URL("login.html", window.location.href);
            loginUrl.searchParams.set("access", "admin");
            loginUrl.searchParams.set("email", response.data.admin.email);
            window.setTimeout(() => {
                window.location.href = loginUrl.href;
            }, 1800);
        } catch (error) {
            message.textContent = error.message || "Não foi possível cadastrar a escola. Confira os dados e tente novamente.";
            window.EduUI?.showToast(error, "error");
        } finally {
            controller.setLoading(false);
        }
    });

    loadPlans();
}
