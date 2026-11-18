<?php

use App\Mcp\Servers\GarimpoServer;
use Laravel\Mcp\Facades\Mcp;

// Login por OAuth (Claude.ai, ChatGPT, Claude Code, Codex, Cursor): descoberta + cadastro automático do cliente.
Mcp::oauthRoutes();

// A IA entra com o token OAuth ou com o token pessoal gerado em /dev (ambos via Passport, guard "api").
Mcp::web('/mcp', GarimpoServer::class)->middleware(['auth:api', 'throttle:120,1']);
