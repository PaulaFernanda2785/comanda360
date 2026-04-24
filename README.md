# MesiMenu

**MesiMenu** é uma plataforma web para gestão de restaurantes, lanchonetes, bares e operações de atendimento por mesa. O sistema une cardápio digital por QR Code, controle de comandas, pedidos, cozinha, pagamentos, caixa, entregas, estoque e administração SaaS em uma aplicação PHP MVC desenvolvida do zero.

O projeto foi criado como uma solução real de operação, não apenas como tela demonstrativa. Ele contempla fluxos completos para o cliente final fazer pedidos pelo celular, para a equipe acompanhar a produção e para o administrador gerenciar a empresa, os usuários, os planos e o financeiro.

## Visão Geral

O MesiMenu resolve um problema comum em pequenos e médios estabelecimentos: centralizar o atendimento digital e a gestão operacional sem depender de várias ferramentas isoladas.

Principais experiências do sistema:

- **Página pública comercial** com apresentação do produto, captação de leads, depoimentos e cadastro de empresas.
- **Cadastro público de empresas** com seleção de plano, pagamento e ativação inicial.
- **Cardápio digital por QR Code** para clientes abrirem comanda, montarem pedidos e acompanharem tickets.
- **Painel administrativo da empresa** para produtos, mesas, comandas, pedidos, cozinha, pagamentos, caixa, entregas, estoque e suporte.
- **Painel SaaS** para gestão de empresas, planos, assinaturas, cobranças, contatos públicos e suporte.
- **Integração com Mercado Pago** para PIX, checkout/cartão, assinaturas e webhooks.

## Módulos

### Cardápio Digital

O cliente acessa o menu por QR Code, escolhe produtos, seleciona adicionais, abre comanda e envia pedidos diretamente para a operação. O fluxo foi pensado para uso em celular, com URLs públicas controladas por empresa, mesa e token.

### Gestão de Pedidos e Cozinha

Os pedidos entram no painel administrativo com status operacionais, envio para cozinha e emissão de tickets. A cozinha tem uma visão própria para acompanhar preparo, andamento e conclusão dos itens.

### Produtos, Categorias e Adicionais

O painel permite cadastrar produtos, organizar categorias, configurar imagens, preços e regras de adicionais. Essa estrutura dá suporte a pedidos com variações, complementos e quantidades controladas.

### Mesas e Comandas

O sistema possui cadastro de mesas, geração de QR Code e controle de comandas abertas. Cada mesa pode ter um fluxo próprio de atendimento e consulta de ticket.

### Pagamentos, Caixa e Entregas

Além do pedido em si, o MesiMenu inclui registro de pagamentos, abertura e fechamento de caixa, emissão de comprovantes e acompanhamento de entregas.

### Estoque

O módulo de estoque permite controlar itens e movimentações, com acesso condicionado ao plano/recurso habilitado para a empresa.

### SaaS e Assinaturas

Há uma camada administrativa para operar o produto como SaaS: empresas, planos, assinaturas, cobranças, pagamentos, suporte e interações públicas. Isso permite que o MesiMenu seja vendido para vários estabelecimentos usando a mesma base da aplicação.

## Arquitetura

O projeto foi estruturado em PHP puro com arquitetura MVC modular:

```text
app/
  Controllers/   Camada HTTP por contexto
  Core/          Núcleo do framework interno
  Middlewares/   Autenticação, perfil, permissões e plano
  Repositories/  Acesso a dados
  Services/      Regras de negócio
  View/          Renderização

resources/views/ Templates da interface
routes/          Definição das rotas
config/          Configurações
public/          Entrada pública da aplicação
storage/         Logs, cache, sessões e arquivos privados
```

A aplicação usa separação entre controllers, services e repositories para manter as regras de negócio fora das views e facilitar evolução por módulo.

## Segurança e Boas Práticas

O sistema inclui cuidados importantes para publicação em hospedagem compartilhada:

- autenticação e controle de permissões por perfil;
- middlewares por contexto de empresa e painel SaaS;
- proteção CSRF em formulários;
- validação de submissões públicas;
- rate limit em rotas públicas sensíveis;
- separação entre arquivos públicos e aplicação privada;
- bloqueio de execução em diretórios de upload;
- validação de caminhos e MIME para imagens públicas;
- proteção contra redirects inseguros e headers inválidos;
- limites de payload em webhooks e pedidos;
- ambiente de produção configurável por `.env`.

## Integrações

- **Mercado Pago**: pagamentos, PIX, checkout recorrente e webhooks.
- **QR Code**: geração de QR para mesas e cardápio digital.
- **MySQL**: persistência dos dados da operação.
- **Apache/mod_rewrite**: roteamento para a aplicação MVC.

## Tecnologias

- PHP 8+
- MySQL
- JavaScript
- HTML/CSS
- Apache
- Node.js apenas para geração de QR Code quando necessário

## Diferenciais do Projeto

Este repositório demonstra uma aplicação com escopo de produto real:

- fluxo público de aquisição e cadastro;
- backoffice SaaS;
- painel operacional para estabelecimento;
- cardápio digital utilizável por cliente final;
- integração de pagamentos;
- controle de acesso por permissões;
- módulos financeiros e operacionais integrados;
- preocupação com deploy seguro em hospedagem.

## Status

Projeto em fase de implantação e refinamento para uso em produção.

## Autoria

Desenvolvido por **Paula Fernanda** como projeto de produto e portfólio, com foco em sistemas web para gestão comercial, automação de atendimento e operação de restaurantes.
