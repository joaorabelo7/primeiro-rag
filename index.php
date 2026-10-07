<?php
// ============================================================
// CONFIGURACAO
// ============================================================

// Endereco do servico FastAPI (RAG).
// 127.0.0.1 = "esta mesma maquina". Se o FastAPI estiver em outro
// servidor, troque pelo endereco dele.
const RAG_URL = 'http://127.0.0.1:8000/query';

// Tempo maximo (em segundos) que o PHP espera pela resposta.
// Alto porque o LLM local pode demorar.
const TIMEOUT = 120;


// ============================================================
// PARTE 1: API
// Este bloco so roda quando o JavaScript envia a pergunta (POST).
// Quando voce apenas abre a pagina no navegador (GET), ele e ignorado.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Avisa ao navegador que a resposta sera JSON, nao HTML
    header('Content-Type: application/json; charset=utf-8');

    // Por padrao o PHP corta scripts que passam de ~30s.
    // Aumentamos para nao cortar a chamada antes do LLM terminar.
    set_time_limit(TIMEOUT + 10);

    // Le o corpo cru da requisicao (o JSON enviado pelo JavaScript).
    // $_POST nao serve aqui, pois so le formularios tradicionais.
    // O "true" converte o JSON em array do PHP.
    $entrada  = json_decode(file_get_contents('php://input'), true);

    // Pega o campo "query". O "?? ''" usa texto vazio se ele nao existir,
    // evitando erro de "chave indefinida". O trim remove espacos das pontas.
    $pergunta = trim($entrada['query'] ?? '');

    // Validacao: nao adianta chamar a IA com uma pergunta vazia
    if ($pergunta === '') {
        http_response_code(400);                          // 400 = requisicao invalida
        echo json_encode(['error' => 'Pergunta vazia']);  // array PHP -> texto JSON
        exit;                                             // encerra o script aqui
    }

    // ---- Chamada ao FastAPI usando cURL ----

    // Inicia uma requisicao apontando para o endereco do RAG
    $ch = curl_init(RAG_URL);

    // Configura a requisicao
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,                                // metodo POST
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'], // avisa que o corpo e JSON
        CURLOPT_POSTFIELDS     => json_encode(['query' => $pergunta]), // corpo: {"query": "..."}
        CURLOPT_RETURNTRANSFER => true,                                // devolve a resposta como texto (em vez de imprimir)
        CURLOPT_TIMEOUT        => TIMEOUT,                             // limite de espera
    ]);

    $resposta = curl_exec($ch);                      // executa a chamada
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE); // codigo HTTP devolvido (200, 500...)
    $erroCurl = curl_error($ch);                     // mensagem de erro do cURL (vazia se deu certo)
    curl_close($ch);                                 // libera os recursos

    // curl_exec devolve false quando NEM CONSEGUIU falar com o FastAPI
    // (servidor desligado, timeout, porta errada...)
    if ($resposta === false) {
        http_response_code(502); // 502 = o servico por tras falhou
        echo json_encode(['error' => 'Não foi possível falar com o serviço de IA: ' . $erroCurl]);
        exit;
    }

    // Repassa ao navegador exatamente o status e o JSON que o FastAPI devolveu
    http_response_code($status);
    echo $resposta;
    exit; // impede que o HTML abaixo seja enviado junto
}

// ============================================================
// PARTE 2: PAGINA
// So chega aqui em requisicoes GET (quando voce abre a URL).
// O "?>" encerra o modo PHP: tudo abaixo e HTML puro.
// ============================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"> <!-- codificacao: evita acentos quebrados -->
<meta name="viewport" content="width=device-width, initial-scale=1"> <!-- ajusta a pagina ao celular -->
<title>Teste do RAG</title>

<style>
  /* Pagina centralizada, com largura maxima e espaco nas laterais */
  body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; color: #222; }
  h1 { font-size: 1.4rem; }

  /* display:block faz o rotulo ocupar a linha inteira */
  label { display: block; margin-top: 16px; font-size: .85rem; color: #555; }

  /* box-sizing faz padding e borda ficarem DENTRO dos 100% de largura */
  textarea { width: 100%; box-sizing: border-box; padding: 10px; font-size: 1rem; border: 1px solid #bbb; border-radius: 6px; }

  button { margin-top: 12px; padding: 10px 20px; font-size: 1rem; border: 0; border-radius: 6px; background: #2563eb; color: #fff; cursor: pointer; }

  /* Visual do botao enquanto esta desativado (aguardando resposta) */
  button:disabled { background: #94a3b8; cursor: wait; }

  /* pre-wrap preserva as quebras de linha da resposta da IA */
  #resposta { margin-top: 20px; padding: 14px; background: #f1f5f9; border-radius: 6px; white-space: pre-wrap; min-height: 24px; }

  /* Classe aplicada pelo JavaScript quando ocorre um erro */
  .erro { color: #b91c1c; }
</style>
</head>
<body>
  <h1>Teste do RAG</h1>

  <!-- O "for" liga o rotulo ao campo com o mesmo id -->
  <label for="pergunta">Pergunta</label>
  <textarea id="pergunta" rows="3" placeholder="Ex.: O que é FastAPI?"></textarea>

  <!-- Ao clicar, chama a funcao enviar() do JavaScript -->
  <button id="btn" onclick="enviar()">Enviar</button>

  <!-- Caixa onde o JavaScript escreve a resposta, o "Pensando..." ou o erro -->
  <div id="resposta"></div>

<script>
// ============================================================
// JAVASCRIPT (roda no navegador)
// ============================================================

// "async" permite usar "await" dentro da funcao,
// esperando a resposta sem travar a pagina.
async function enviar() {

  // Busca na pagina os elementos que vamos usar
  const btn = document.getElementById('btn');
  const el = document.getElementById('resposta');

  // Le o que foi digitado (trim remove espacos das pontas)
  const pergunta = document.getElementById('pergunta').value.trim();

  // Se estiver vazio, avisa e para (return) sem chamar o servidor
  if (!pergunta) { el.textContent = 'Digite uma pergunta.'; return; }

  btn.disabled = true;   // desativa o botao para evitar envios repetidos
  el.className = '';     // remove a classe de erro de uma tentativa anterior
  el.textContent = 'Pensando... (a primeira resposta pode demorar)';

  try {
    // Envia a pergunta para ESTE MESMO arquivo PHP
    // (window.location.pathname = caminho da pagina atual).
    // No PHP, isso cai no bloco "if POST" la em cima.
    const r = await fetch(window.location.pathname, {
      method: 'POST',                                     // metodo POST
      headers: { 'Content-Type': 'application/json' },    // avisa que o corpo e JSON
      body: JSON.stringify({ query: pergunta })           // objeto JS -> texto JSON
    });

    // Converte a resposta (texto JSON) em objeto JavaScript
    const dados = await r.json();

    // r.ok e false para status de erro (400, 500, 502...).
    // Nesse caso lanca um erro com a melhor mensagem disponivel:
    //   dados.detail -> erros do FastAPI
    //   dados.error  -> erros do PHP
    //   'Erro ' + status -> se nenhuma das duas existir
    if (!r.ok) throw new Error(dados.detail || dados.error || ('Erro ' + r.status));

    // Sucesso: mostra a resposta. textContent insere como texto puro,
    // o que impede que HTML vindo da IA seja executado na pagina.
    el.textContent = dados.response;

  } catch (e) {
    // Qualquer falha acima (rede, JSON invalido, o "throw") cai aqui
    el.className = 'erro'; // texto vermelho
    el.textContent = 'Erro: ' + e.message;

  } finally {
    // Roda SEMPRE, com sucesso ou erro: reativa o botao
    btn.disabled = false;
  }
}

// Atalho de teclado na caixa de pergunta:
// Enter envia; Shift+Enter quebra a linha normalmente.
document.getElementById('pergunta').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault(); // cancela a quebra de linha que o Enter faria
    enviar();
  }
});
</script>
</body>
</html>