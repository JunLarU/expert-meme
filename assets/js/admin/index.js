import "../../sass/admin/app.scss";
import { bootstrapWhisDefaults } from "../helpers/framework-defaults";
import initAdminApiTokens from "../sections/admin-api-tokens";

import initAdminDashboard from "./sections/admin-dashboard";

export {
  WHIS_DEFAULT_OPTIONS,
  defineWhisDefaults,
  getWhisDefaults,
  resetWhisDefaults,
  createWhisConfig,
  initWhisDefaults,
  bootstrapWhisDefaults,
  getWhisInstance,
  refreshWhisDefaults,
  destroyWhisDefaults,
} from "../helpers/framework-defaults";

bootstrapWhisDefaults();

document.addEventListener("DOMContentLoaded", () => {
  initAdminDashboard();
  initAdminApiTokens();  

  
});