<?php

use App\Mcp\Servers\GarimpoServer;
use Laravel\Mcp\Facades\Mcp;

// Cada usuário conecta a própria IA (Claude Code, Cursor, Codex...) com o token gerado em /conectar-ia.
Mcp::web('/mcp', GarimpoServer::class)->middleware(['auth:sanctum', 'throttle:120,1']);
