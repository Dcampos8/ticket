<?php
// Requiere que la página que lo incluya ya tenga sesión iniciada y BASE_URL definida.
if (!isset($_SESSION['logueado'])) { return; }
$chat_base = defined('BASE_URL') ? BASE_URL : '/';
?>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/shared__chat_widget.css?v=<?= filemtime(__DIR__ . '/../css/pages/shared__chat_widget.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../css/brand.css') ?>">
<button class="civ-chat-burbuja" id="civChatBurbuja" title="Chat interno">
    <i class="fa-solid fa-comments"></i>
    <span class="civ-badge" id="civChatBadgeTotal" style="display:none;">0</span>
</button>

<div class="civ-chat-panel" id="civChatPanel">
    <div class="civ-chat-header">
        <b><i class="fa-solid fa-comments"></i> Chat Interno</b>
        <div class="civ-iconos">
            <i class="fa-regular fa-bell" id="civChatBell" title="Activar notificaciones"></i>
            <i class="fa-solid fa-xmark" id="civChatCerrar" title="Cerrar"></i>
        </div>
    </div>
    <div class="civ-chat-tabs">
        <button class="civ-activo" id="civTabGrupo" onclick="civChatMostrarTab('grupo')">
            General <span class="civ-mini-badge" id="civBadgeGrupo" style="display:none;">0</span>
        </button>
        <button id="civTabDirectos" onclick="civChatMostrarTab('directos')">
            Directos <span class="civ-mini-badge" id="civBadgeDirectos" style="display:none;">0</span>
        </button>
    </div>

    <!-- Vista: mensajes (grupo o conversación privada) -->
    <div id="civVistaMensajes" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
        <div class="civ-chat-volver" id="civVolverLista" style="display:none;" onclick="civChatVolverLista()">
            <i class="fa-solid fa-arrow-left"></i> Directos
        </div>
        <div class="civ-chat-body" id="civChatBody"></div>
        <div class="civ-chat-input">
            <input type="text" id="civChatInput" placeholder="Escribe un mensaje..." maxlength="2000">
            <button onclick="civChatEnviar()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>

    <!-- Vista: lista de usuarios para Directos -->
    <div id="civVistaUsuarios" style="display:none; flex:1; overflow-y:auto;">
        <div class="civ-chat-buscar">
            <input type="text" id="civBuscarUsuario" placeholder="Buscar persona..." oninput="civChatFiltrarUsuarios()">
        </div>
        <div class="civ-chat-lista-usuarios" id="civListaUsuarios"></div>
    </div>
</div>

<script>
(function () {
    const BASE = "<?= $chat_base ?>";
    let civTab = 'grupo';
    let civConversacionActual = null; // {id, nombre} cuando estamos en un chat privado
    let civUltimoIdGrupo = 0;
    const civUltimoIdPrivado = {}; // por usuario_id
    let civUsuariosCache = [];
    let civNoLeidosPorUsuario = {};
    let civPanelAbierto = false;
    let civUltimoAvisoGrupo = 0;

    function $(id) { return document.getElementById(id); }

    window.civChatMostrarTab = function (tab) {
        civTab = tab;
        $('civTabGrupo').classList.toggle('civ-activo', tab === 'grupo');
        $('civTabDirectos').classList.toggle('civ-activo', tab === 'directos');

        if (tab === 'grupo') {
            civConversacionActual = null;
            $('civVistaUsuarios').style.display = 'none';
            $('civVistaMensajes').style.display = 'flex';
            $('civVolverLista').style.display = 'none';
            civCargarMensajes('grupo', null, true);
        } else {
            $('civVistaMensajes').style.display = 'none';
            $('civVistaUsuarios').style.display = 'block';
            civCargarUsuarios();
        }
    };

    window.civChatVolverLista = function () {
        civConversacionActual = null;
        $('civVistaMensajes').style.display = 'none';
        $('civVistaUsuarios').style.display = 'block';
    };

    function civAbrirConversacion(usuario) {
        civConversacionActual = usuario;
        civNoLeidosPorUsuario[usuario.id] = 0;
        $('civVistaUsuarios').style.display = 'none';
        $('civVistaMensajes').style.display = 'flex';
        $('civVolverLista').style.display = 'block';
        $('civChatBody').innerHTML = '';
        civCargarMensajes('privado', usuario.id, true);
    }

    function civCargarUsuarios() {
        fetch(BASE + 'shared/backend/chat_usuarios.php')
            .then(r => r.json())
            .then(data => {
                civUsuariosCache = Array.isArray(data) ? data : [];
                civRenderUsuarios(civUsuariosCache);
            })
            .catch(() => {});
    }

    function civRenderUsuarios(lista) {
        const cont = $('civListaUsuarios');
        if (lista.length === 0) {
            cont.innerHTML = '<div class="civ-chat-vacio">No hay personas disponibles.</div>';
            return;
        }

        // Ordenar: primero quienes tienen mensajes sin leer (más recientes arriba)
        const ordenada = [...lista].sort((a, b) => {
            const na = civNoLeidosPorUsuario[a.id] || 0;
            const nb = civNoLeidosPorUsuario[b.id] || 0;
            if (na > 0 && nb === 0) return -1;
            if (nb > 0 && na === 0) return 1;
            return 0;
        });

        cont.innerHTML = ordenada.map(u => {
            const noLeidos = civNoLeidosPorUsuario[u.id] || 0;
            const tieneNuevo = noLeidos > 0;
            return `
            <div class="civ-chat-item-usuario" onclick='civChatAbrirDirecto(${u.id}, ${JSON.stringify(u.nombre)})'>
                <div>
                    <span class="civ-nombre" style="${tieneNuevo ? 'font-weight:800; color:#e31e24;' : ''}">${civEscape(u.nombre)}</span>
                    <span class="civ-rol">${civEscape(u.area || u.rol || '')}</span>
                </div>
                ${tieneNuevo ? `<span class="civ-mini-badge">${noLeidos}</span>` : ''}
            </div>
        `;
        }).join('');
    }

    window.civChatAbrirDirecto = function (id, nombre) {
        civAbrirConversacion({ id, nombre });
    };

    window.civChatFiltrarUsuarios = function () {
        const q = $('civBuscarUsuario').value.toLowerCase();
        civRenderUsuarios(civUsuariosCache.filter(u => u.nombre.toLowerCase().includes(q)));
    };

    function civEscape(str) {
        const d = document.createElement('div');
        d.innerText = str || '';
        return d.innerHTML;
    }

    function civFormatHora(fechaStr) {
        const f = new Date(fechaStr.replace(' ', 'T'));
        return f.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }

    function civPintarMensajes(mensajes) {
        const body = $('civChatBody');
        mensajes.forEach(m => {
            const div = document.createElement('div');
            div.className = 'civ-chat-msg ' + (m.propio ? 'civ-propio' : 'civ-otro');
            div.innerHTML = (!m.propio && civTab === 'grupo' ? `<span class="civ-autor">${civEscape(m.remitente_nombre)}</span>` : '')
                + civEscape(m.mensaje)
                + `<span class="civ-hora">${civFormatHora(m.fecha)}</span>`;
            body.appendChild(div);
        });
        if (mensajes.length > 0) {
            body.scrollTop = body.scrollHeight;
        }
    }

    function civCargarMensajes(tipo, con, reiniciar) {
        let url = BASE + 'shared/backend/chat_obtener.php?tipo=' + tipo;
        if (tipo === 'privado') url += '&con=' + con;
        if (reiniciar) {
            $('civChatBody').innerHTML = '';
            url += '&despues_de=0';
        } else {
            const desde = tipo === 'grupo' ? civUltimoIdGrupo : (civUltimoIdPrivado[con] || 0);
            url += '&despues_de=' + desde;
        }
        fetch(url).then(r => r.json()).then(mensajes => {
            if (!Array.isArray(mensajes) || mensajes.length === 0) return;
            civPintarMensajes(mensajes);
            const maxId = Math.max(...mensajes.map(m => m.id));
            if (tipo === 'grupo') civUltimoIdGrupo = Math.max(civUltimoIdGrupo, maxId);
            else civUltimoIdPrivado[con] = Math.max(civUltimoIdPrivado[con] || 0, maxId);
        });
    }

    window.civChatEnviar = function () {
        const input = $('civChatInput');
        const texto = input.value.trim();
        if (!texto) return;

        const body = new URLSearchParams({ tipo: civTab === 'grupo' ? 'grupo' : 'privado', mensaje: texto });
        if (civTab === 'directos' && civConversacionActual) {
            body.set('destinatario_id', civConversacionActual.id);
        } else if (civTab === 'directos' && !civConversacionActual) {
            return; // no hay conversación seleccionada
        }

        input.value = '';
        fetch(BASE + 'shared/backend/chat_enviar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        }).then(r => r.json()).then(res => {
            if (res.success) {
                if (civTab === 'grupo') civCargarMensajes('grupo', null, false);
                else civCargarMensajes('privado', civConversacionActual.id, false);
            }
        });
    };

    $('civChatInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') civChatEnviar();
    });

    // --- Abrir / cerrar panel ---
    $('civChatBurbuja').addEventListener('click', function () {
        civPanelAbierto = !civPanelAbierto;
        $('civChatPanel').classList.toggle('civ-abierto', civPanelAbierto);
        if (civPanelAbierto && civTab === 'grupo' && !civConversacionActual) {
            civCargarMensajes('grupo', null, civUltimoIdGrupo === 0);
        }
    });
    $('civChatCerrar').addEventListener('click', function () {
        civPanelAbierto = false;
        $('civChatPanel').classList.remove('civ-abierto');
    });

    // --- Notificaciones tipo Windows (Notification API del navegador) ---
    function civActualizarIconoCampana() {
        const bell = $('civChatBell');
        if (!('Notification' in window)) { bell.style.display = 'none'; return; }
        if (Notification.permission === 'granted') {
            bell.classList.remove('fa-regular', 'fa-bell-slash');
            bell.classList.add('fa-solid', 'fa-bell');
            bell.title = 'Notificaciones activadas';
        } else {
            bell.classList.remove('fa-solid');
            bell.classList.add('fa-regular', 'fa-bell');
            bell.title = 'Activar notificaciones de escritorio';
        }
    }
    $('civChatBell').addEventListener('click', function () {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(civActualizarIconoCampana);
        } else if (Notification.permission === 'denied') {
            alert('Los avisos de escritorio están bloqueados para este sitio. Actívalos desde el ícono de candado/información junto a la URL del navegador.');
        }
    });
    civActualizarIconoCampana();

    function civMostrarNotificacionEscritorio(titulo, cuerpo) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        try {
            const n = new Notification(titulo, {
                body: cuerpo,
                icon: BASE + 'img/icono.png',
                tag: 'civ-chat-' + Date.now()
            });
            n.onclick = function () {
                window.focus();
                if (!civPanelAbierto) $('civChatBurbuja').click();
                n.close();
            };
        } catch (e) {}
    }

    // --- Polling del resumen (badge + disparo de notificaciones) ---
    // AJUSTE: intervalo subido de 6s a 20s, y se pausa por completo cuando la
    // pestaña está en segundo plano (Page Visibility API). Esto es lo que
    // estaba disparando cientos de conexiones MySQL por hora y agotando el
    // límite de max_connections_per_hour.
    let civIntervaloPolling = null;

    function civIniciarPolling() {
        if (civIntervaloPolling) return; // ya está corriendo, no duplicar
        civIntervaloPolling = setInterval(civActualizarBadge, 20000); // antes: 6000
    }

    function civDetenerPolling() {
        if (civIntervaloPolling) {
            clearInterval(civIntervaloPolling);
            civIntervaloPolling = null;
        }
    }

    function civActualizarBadge() {
        fetch(BASE + 'shared/backend/chat_resumen.php').then(r => r.json()).then(data => {
            if (!data.success) return;

            const badgeGrupo = $('civBadgeGrupo');
            const badgeDirectos = $('civBadgeDirectos');
            const badgeTotal = $('civChatBadgeTotal');

            if (data.grupo_no_leidos > 0) {
                badgeGrupo.style.display = 'inline-block';
                badgeGrupo.innerText = data.grupo_no_leidos;
            } else {
                badgeGrupo.style.display = 'none';
            }

            const totalDirectos = data.privados.reduce((s, p) => s + p.no_leidos, 0);
            if (totalDirectos > 0) {
                badgeDirectos.style.display = 'inline-block';
                badgeDirectos.innerText = totalDirectos;
            } else {
                badgeDirectos.style.display = 'none';
            }

            // Refrescar mapa de no-leídos por usuario y, si la lista de Directos está visible, repintarla
            civNoLeidosPorUsuario = {};
            data.privados.forEach(p => { civNoLeidosPorUsuario[p.usuario_id] = p.no_leidos; });
            const viendoListaDirectos = civPanelAbierto && civTab === 'directos' && !civConversacionActual;
            if (viendoListaDirectos && civUsuariosCache.length > 0) {
                civRenderUsuarios(civUsuariosCache);
            }

            if (data.total_no_leidos > 0) {
                badgeTotal.style.display = 'flex';
                badgeTotal.innerText = data.total_no_leidos > 99 ? '99+' : data.total_no_leidos;
            } else {
                badgeTotal.style.display = 'none';
            }

            // Notificación del grupo si hay mensaje nuevo de alguien más y no lo estamos viendo activamente
            const viendoGrupoActivo = civPanelAbierto && civTab === 'grupo';
            if (data.ultimo_msg_grupo && data.ultimo_msg_grupo.id > civUltimoAvisoGrupo) {
                civUltimoAvisoGrupo = data.ultimo_msg_grupo.id;
                if (!viendoGrupoActivo && data.grupo_no_leidos > 0) {
                    civMostrarNotificacionEscritorio('General: ' + data.ultimo_msg_grupo.remitente_nombre, data.ultimo_msg_grupo.mensaje);
                }
            }

            // Notificación de privados
            data.privados.forEach(p => {
                const viendoEstaConversacion = civPanelAbierto && civTab === 'directos' && civConversacionActual && civConversacionActual.id === p.usuario_id;
                if (!viendoEstaConversacion) {
                    const key = 'civ_last_priv_' + p.usuario_id;
                    if (!window[key] || window[key] < p.ultimo_id) {
                        window[key] = p.ultimo_id;
                        civMostrarNotificacionEscritorio(p.nombre, p.ultimo_mensaje);
                    }
                } else {
                    // si la estamos viendo, refrescamos esa conversación
                    civCargarMensajes('privado', p.usuario_id, false);
                }
            });

            // Si el grupo está abierto y activo, refrescar mensajes nuevos también
            if (viendoGrupoActivo) civCargarMensajes('grupo', null, false);
        }).catch(() => {});
    }

    // Pausa/reanuda el polling según la visibilidad de la pestaña.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            civDetenerPolling();
        } else {
            civActualizarBadge(); // refresca de inmediato al volver a la pestaña
            civIniciarPolling();
        }
    });

    civActualizarBadge();
    civIniciarPolling();
})();
</script>