import AuthGuard from "../../_common/scripts/auth-guard.js";
import SessionStorage from "../../_common/scripts/storage.js";
import Profile from "../../_common/scripts/profile.js";

// Perfil do administrador: global-perfil.html (tipo 1) e perfil.html (tipo 2).
const isGlobal = window.location.pathname.endsWith("global-perfil.html");
const guard = new AuthGuard("../public/login.html", "admin", isGlobal ? 1 : 2);

if (guard.protect()) {
    new Profile(new SessionStorage("admin"), { admin: true }).init();
}
