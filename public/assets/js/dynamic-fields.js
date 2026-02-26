(function () {
  "use strict";

  const BASE = "/vilcon-systemon/public/api";
  const FORM_ACTIONS = new Set(["criar_os_manual", "criar_pedido", "criar_manutencao", "criar_avaria"]);
  const PRIORITY_NAMES = ["prioridade", "prioridade_rep", "prioridade_pm"];

  function norm(v) {
    return String(v || "").trim().toLowerCase();
  }

  function firstField(form, names) {
    for (let i = 0; i < names.length; i += 1) {
      const el = form.querySelector('input[name="' + names[i] + '"]');
      if (el) return el;
    }
    return null;
  }

  function firstFieldContains(form, parts) {
    const inputs = form.querySelectorAll('input[name]:not([type="hidden"])');
    for (let i = 0; i < inputs.length; i += 1) {
      const n = String(inputs[i].name || "").toLowerCase();
      for (let j = 0; j < parts.length; j += 1) {
        if (n.indexOf(parts[j]) !== -1) return inputs[i];
      }
    }
    return null;
  }

  function fetchJson(url) {
    return fetch(url, { credentials: "same-origin" }).then((r) => r.json());
  }

  function parsePriority(v) {
    const t = norm(v);
    if (t === "critica" || t === "urgente") return "Critica";
    if (t === "alta") return "Alta";
    if (t === "media" || t === "normal") return "Media";
    return "Baixa";
  }

  function ensureStyle() {
    if (document.getElementById("dynamic-fields-style")) return;
    const css = document.createElement("style");
    css.id = "dynamic-fields-style";
    css.textContent = [
      ".dyn-wrap{display:grid;gap:6px}",
      ".dyn-search,.dyn-select{width:100%;min-height:38px;border:1px solid #d0d7e2;border-radius:8px;padding:0 10px;background:#fff}",
      ".dyn-select:focus,.dyn-search:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.14)}",
      ".dyn-status{font-size:12px;color:#64748b}",
      ".dyn-status.error{color:#b91c1c}",
      ".prio-critica{border-color:#dc2626!important;box-shadow:0 0 0 3px rgba(220,38,38,.16)!important;background:#fff5f5}",
      ".prio-justif{display:none;margin-top:6px}",
      ".prio-justif.is-open{display:block}",
      ".prio-justif textarea{width:100%;min-height:80px;border:1px solid #f1b5b5;border-radius:8px;padding:8px}",
      ".dyn-inline{display:flex;gap:8px;align-items:center}",
      ".dyn-btn{min-height:34px;padding:0 10px;border:1px solid #d0d7e2;border-radius:8px;background:#fff;cursor:pointer}"
    ].join("");
    document.head.appendChild(css);
  }

  function buildLookup(form, fieldInput, cfg) {
    if (!fieldInput || fieldInput.dataset.dynReady === "1") return;
    fieldInput.dataset.dynReady = "1";

    const wrapper = document.createElement("div");
    wrapper.className = "dyn-wrap";

    const tools = document.createElement("div");
    tools.className = "dyn-inline";

    const search = document.createElement("input");
    search.type = "search";
    search.className = "dyn-search";
    search.placeholder = cfg.searchPlaceholder || "Pesquisar...";

    const addBtn = cfg.allowCreate ? document.createElement("button") : null;
    if (addBtn) {
      addBtn.type = "button";
      addBtn.className = "dyn-btn";
      addBtn.textContent = "+ Adicionar";
      tools.appendChild(addBtn);
    }

    tools.appendChild(search);

    const select = document.createElement("select");
    select.className = "dyn-select";
    select.innerHTML = '<option value="">' + (cfg.placeholder || "Selecione") + "</option>";

    const status = document.createElement("div");
    status.className = "dyn-status";
    status.textContent = "A carregar...";

    const idField = document.createElement("input");
    idField.type = "hidden";
    idField.name = cfg.idFieldName;

    fieldInput.type = "hidden";
    fieldInput.readOnly = true;

    wrapper.appendChild(tools);
    wrapper.appendChild(select);
    wrapper.appendChild(status);
    wrapper.appendChild(idField);
    fieldInput.parentNode.insertBefore(wrapper, fieldInput.nextSibling);

    let cache = [];

    function render(items, filterText) {
      const ft = norm(filterText);
      const filtered = ft ? items.filter((it) => norm(it.label).includes(ft)) : items;
      select.innerHTML = '<option value="">' + (cfg.placeholder || "Selecione") + "</option>";
      filtered.forEach((item) => {
        const op = document.createElement("option");
        op.value = String(item.id || "");
        op.textContent = String(item.label || "");
        op.dataset.payload = JSON.stringify(item);
        select.appendChild(op);
      });
      status.textContent = filtered.length === 0 ? "Nenhum resultado encontrado" : filtered.length + " resultado(s)";
      status.classList.remove("error");
    }

    function load(searchText) {
      status.textContent = "A carregar...";
      status.classList.remove("error");
      const u = BASE + "/" + cfg.endpoint + "?search=" + encodeURIComponent(searchText || "");
      fetchJson(u)
        .then((data) => {
          cache = Array.isArray(data && data.items) ? data.items : [];
          render(cache, search.value);
        })
        .catch(() => {
          status.textContent = "Falha ao carregar dados";
          status.classList.add("error");
        });
    }

    search.addEventListener("input", () => {
      if (cache.length > 0) {
        render(cache, search.value);
      } else {
        load(search.value);
      }
    });

    select.addEventListener("change", () => {
      const op = select.options[select.selectedIndex];
      if (!op || !op.value) {
        idField.value = "";
        fieldInput.value = "";
        search.value = "";
        if (typeof cfg.onClear === "function") {
          cfg.onClear(form);
        }
        return;
      }
      idField.value = op.value;
      let payload = {};
      try {
        payload = JSON.parse(op.dataset.payload || "{}");
      } catch (_e) {}

      if (cfg.assignValue) {
        fieldInput.value = cfg.assignValue(payload);
      } else {
        fieldInput.value = String(payload.nome || payload.label || "");
      }
      if (typeof cfg.searchValueOnSelect === "function") {
        search.value = String(cfg.searchValueOnSelect(payload, op) || "");
      } else {
        search.value = String(op.textContent || "");
      }

      if (typeof cfg.onSelect === "function") {
        cfg.onSelect(payload, form);
      }
    });

    if (addBtn) {
      addBtn.addEventListener("click", () => {
        const nome = String(search.value || "").trim();
        if (!nome) {
          status.textContent = "Digite o nome da localizacao para adicionar";
          status.classList.add("error");
          return;
        }
        const fd = new FormData();
        fd.append("nome", nome);
        status.textContent = "A criar localizacao...";
        fetch(BASE + "/localizacoes.php", { method: "POST", body: fd, credentials: "same-origin" })
          .then((r) => r.json())
          .then((resp) => {
            if (!resp || !resp.ok || !resp.item) {
              throw new Error("Falha");
            }
            cache.unshift(resp.item);
            render(cache, "");
            const value = String(resp.item.id || "");
            select.value = value;
            select.dispatchEvent(new Event("change"));
            status.textContent = "Localizacao adicionada com sucesso";
            status.classList.remove("error");
          })
          .catch(() => {
            status.textContent = "Nao foi possivel adicionar localizacao";
            status.classList.add("error");
          });
      });
    }

    form.addEventListener("submit", (ev) => {
      if (cfg.required && !idField.value) {
        ev.preventDefault();
        status.textContent = cfg.invalidMessage || "Selecione um valor valido da lista.";
        status.classList.add("error");
      }
    });

    load("");
  }

  function attachPriority(form) {
    PRIORITY_NAMES.forEach(function (name) {
      const sel = form.querySelector('select[name="' + name + '"]');
      if (!sel) return;

      const current = parsePriority(sel.value);
      sel.innerHTML = ["Baixa", "Media", "Alta", "Critica"].map((p) => '<option value="' + p + '">' + p + "</option>").join("");
      sel.value = current;

      const justifName = name === "prioridade_rep" ? "justificativa_prioridade_rep" : (name === "prioridade_pm" ? "justificativa_prioridade_pm" : "justificativa_prioridade");
      let justif = form.querySelector('[data-prio-justif="' + name + '"]');
      if (!justif) {
        justif = document.createElement("div");
        justif.dataset.prioJustif = name;
        justif.className = "prio-justif";
        justif.innerHTML = '<label>Justificativa da prioridade critica</label><textarea name="' + justifName + '" placeholder="Explique a urgencia..."></textarea>';
        sel.parentNode.appendChild(justif);
      }

      function sync() {
        const critical = sel.value === "Critica";
        sel.classList.toggle("prio-critica", critical);
        justif.classList.toggle("is-open", critical);
        const ta = justif.querySelector("textarea");
        if (ta) ta.required = critical;
      }

      sel.addEventListener("change", sync);
      sync();
    });
  }

  function applyToForm(form) {
    const acao = form.querySelector('input[name="acao"]');
    const hasKnownAction = !!(acao && FORM_ACTIONS.has(String(acao.value || "")));
    const hasDynamicFields = !!form.querySelector('input[name="ativo_matricula"], input[name="matricula"], input[name="solicitante"], input[name="localizacao"], input[name="condutor"], input[name="motorista"], input[name="viatura_id_rep"], input[name="matricula_rep"], input[name="solicitante_rep"], input[name="localizacao_rep"], input[name="condutor_rep"], input[name="viatura_id_pm"], input[name="matricula_pm"], input[name="solicitante_pm"], input[name="localizacao_pm"], input[name*="projeto"], input[name*="fornecedor"], input[name*="cliente"], input[name*="responsavel"], input[name*="inspector"], input[name*="supervisor"], input[name*="mecanico"], input[name*="colaborador"]');
    if (!hasKnownAction && !hasDynamicFields) return;
    if (String(form.method || "get").toLowerCase() !== "post") return;

    attachPriority(form);

    const vehicleSource = firstField(form, ["ativo_matricula", "matricula", "matricula_rep", "matricula_pm", "matricula_os"]) ||
      firstField(form, ["viatura_id_rep", "viatura_id_pm", "viatura_id_os", "viatura_id"]);
    if (vehicleSource) {
      const idFieldName = vehicleSource.name + "_ref_id";
      buildLookup(form, vehicleSource, {
        endpoint: "viaturas.php",
        idFieldName: idFieldName,
        placeholder: "Selecione a viatura/equipamento",
        searchPlaceholder: "Pesquisar matricula ou nome...",
        required: hasKnownAction || !!vehicleSource.required,
        invalidMessage: "Selecione uma viatura valida da lista.",
        searchValueOnSelect: (p) => String(p.matricula || ""),
        assignValue: function (p) {
          if (String(vehicleSource.name || "").indexOf("viatura_id") !== -1) {
            return String(p.nome || p.matricula || "");
          }
          return String(p.matricula || "");
        },
        onSelect: (p, formEl) => {
          ["viatura_id", "viatura_id_rep", "viatura_id_pm", "viatura_id_os"].forEach((n) => {
            const f = formEl.querySelector('input[name="' + n + '"]');
            if (f && f !== vehicleSource) f.value = String(p.nome || p.matricula || "");
          });
          ["matricula", "matricula_rep", "matricula_pm", "matricula_os", "ativo_matricula"].forEach((n) => {
            const f = formEl.querySelector('input[name="' + n + '"]');
            if (f && f !== vehicleSource) {
              f.value = String(p.matricula || "");
              f.readOnly = true;
            }
          });

          const eq = formEl.querySelector('input[name="tipo_equipamento"], input[name="equipamento"], input[name="tipo_equipamento_pm"]');
          if (eq) {
            if (!eq.value) eq.value = String(p.nome || "");
            eq.readOnly = true;
          }

          const km = formEl.querySelector('input[name="km_atual"], input[name="km_atual_rep"], input[name="km_atual_os"]');
          if (km) km.value = String(Number(p.km_atual || 0));

          const cond = formEl.querySelector('input[name="condutor"], input[name="motorista"], input[name="condutor_rep"], input[name="condutor_os"]');
          if (cond && !cond.value && p.condutor_padrao) {
            cond.value = String(p.condutor_padrao || "");
          }
        },
        onClear: (formEl) => {
          ["viatura_id", "viatura_id_rep", "viatura_id_pm", "viatura_id_os", "matricula", "matricula_rep", "matricula_pm", "matricula_os", "ativo_matricula"].forEach((n) => {
            const f = formEl.querySelector('input[name="' + n + '"]');
            if (f && f !== vehicleSource) f.value = "";
          });
        }
      });
    }

    const solicitante = firstField(form, ["solicitante", "solicitante_rep", "solicitante_pm", "solicitante_os"]);
    if (solicitante) {
      buildLookup(form, solicitante, {
        endpoint: "utilizadores.php",
        idFieldName: solicitante.name + "_id",
        placeholder: "Selecione o solicitante",
        searchPlaceholder: "Pesquisar nome/departamento...",
        required: hasKnownAction || !!solicitante.required,
        invalidMessage: "Selecione um solicitante valido da lista.",
        assignValue: (p) => String(p.nome || "")
      });
    }

    const localizacao = firstField(form, ["localizacao", "localizacao_rep", "localizacao_pm", "local_saida", "destino"]);
    if (localizacao) {
      buildLookup(form, localizacao, {
        endpoint: "localizacoes.php",
        idFieldName: localizacao.name + "_id",
        placeholder: "Selecione a localizacao",
        searchPlaceholder: "Pesquisar localizacao...",
        required: hasKnownAction || !!localizacao.required,
        invalidMessage: "Selecione uma localizacao valida da lista.",
        allowCreate: true,
        assignValue: (p) => String(p.nome || "")
      });
    }

    const condutor = firstField(form, ["condutor", "motorista", "condutor_rep", "condutor_os", "condutor_chk", "motorista_diesel"]);
    if (condutor) {
      buildLookup(form, condutor, {
        endpoint: "motoristas.php",
        idFieldName: condutor.name + "_id",
        placeholder: "Selecione o condutor",
        searchPlaceholder: "Pesquisar nome ou carta...",
        required: hasKnownAction || !!condutor.required,
        invalidMessage: "Selecione um condutor valido da lista.",
        assignValue: (p) => String(p.nome || "")
      });
    }

    const projeto = firstFieldContains(form, ["projeto", "projecto"]);
    if (projeto) {
      buildLookup(form, projeto, {
        endpoint: "projetos.php",
        idFieldName: projeto.name + "_id",
        placeholder: "Selecione o projeto",
        searchPlaceholder: "Pesquisar projeto...",
        required: !!projeto.required,
        invalidMessage: "Selecione um projeto valido da lista.",
        assignValue: (p) => String(p.codigo || p.nome || "")
      });
    }

    const fornecedor = firstFieldContains(form, ["fornecedor"]);
    if (fornecedor) {
      buildLookup(form, fornecedor, {
        endpoint: "fornecedores.php",
        idFieldName: fornecedor.name + "_id",
        placeholder: "Selecione o fornecedor",
        searchPlaceholder: "Pesquisar fornecedor...",
        required: !!fornecedor.required,
        invalidMessage: "Selecione um fornecedor valido da lista.",
        assignValue: (p) => String(p.nome || "")
      });
    }

    const cliente = firstFieldContains(form, ["cliente", "empresa_cliente"]);
    if (cliente) {
      buildLookup(form, cliente, {
        endpoint: "clientes.php",
        idFieldName: cliente.name + "_id",
        placeholder: "Selecione o cliente",
        searchPlaceholder: "Pesquisar cliente...",
        required: !!cliente.required,
        invalidMessage: "Selecione um cliente valido da lista.",
        assignValue: (p) => String(p.nome || "")
      });
    }

    const funcionario = firstFieldContains(form, ["responsavel", "inspector", "supervisor", "mecanico", "mecânico", "colaborador", "funcionario"]);
    if (funcionario) {
      buildLookup(form, funcionario, {
        endpoint: "funcionarios.php",
        idFieldName: funcionario.name + "_id",
        placeholder: "Selecione o funcionario",
        searchPlaceholder: "Pesquisar funcionario...",
        required: !!funcionario.required,
        invalidMessage: "Selecione um funcionario valido da lista.",
        assignValue: (p) => String(p.nome || "")
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    ensureStyle();
    document.querySelectorAll("form").forEach(applyToForm);
  });
})();
