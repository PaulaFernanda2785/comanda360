<?php
declare(strict_types=1);

namespace App\Services\Marketing;

use App\Repositories\CompanyRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Shared\PlanFeatureCatalogService;
use Throwable;

final class LandingPageService
{
    public function __construct(
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly PlanFeatureCatalogService $featureCatalog = new PlanFeatureCatalogService(),
        private readonly \App\Repositories\PublicInteractionRepository $publicInteractions = new \App\Repositories\PublicInteractionRepository()
    ) {}

    public function build(): array
    {
        try {
            $companySummary = $this->companies->summary();
            $subscriptionSummary = $this->subscriptions->summary();
            $plans = $this->normalizePlans($this->plans->listActiveForPublicCatalog());
            $publishedInteractions = $this->publicInteractions->listPublished(6);
        } catch (Throwable) {
            $companySummary = [];
            $subscriptionSummary = [];
            $plans = [];
            $publishedInteractions = [];
        }
        $featuredPlans = array_values(array_filter(
            $plans,
            static fn (array $plan): bool => !empty($plan['is_featured'])
        ));
        $recommendedPlans = array_values(array_filter(
            $plans,
            static fn (array $plan): bool => !empty($plan['is_recommended'])
        ));

        return [
            'seo' => $this->buildSeo(),
            'navigation' => $this->navigation(),
            'hero_metrics' => [
                [
                    'value' => (string) ((int) ($companySummary['active_companies'] ?? 0) + (int) ($companySummary['testing_companies'] ?? 0)),
                    'label' => 'operações em andamento',
                ],
                [
                    'value' => (string) ((int) ($subscriptionSummary['active_subscriptions'] ?? 0) + (int) ($subscriptionSummary['trial_subscriptions'] ?? 0)),
                    'label' => 'assinaturas monitoradas',
                ],
                [
                    'value' => 'PIX + cartão',
                    'label' => 'cobrança mensal e anual',
                ],
                [
                    'value' => 'Pronto para SEO',
                    'label' => 'estrutura pronta para indexação',
                ],
            ],
            'about_highlights' => [
                [
                    'value' => '4 áreas',
                    'label' => 'público, operacional, administrativo e SaaS',
                ],
                [
                    'value' => 'Multiempresa',
                    'label' => 'cada cliente opera com identidade, catálogo e regras próprias',
                ],
                [
                    'value' => 'PIX + cartão',
                    'label' => 'pagamentos do negócio e cobrança recorrente em um mesmo ecossistema',
                ],
            ],
            'about_capabilities' => [
                'Cardápio digital responsivo',
                'QR Code por mesa',
                'Comandas, pedidos e cozinha',
                'Caixa, pagamentos e fechamento',
                'Delivery com zonas de entrega',
                'Estoque, produtos e adicionais',
                'Usuários, suporte e permissões',
                'Planos, assinaturas e cobrança',
            ],
            'about_modules' => [
                [
                    'eyebrow' => 'Cliente final',
                    'title' => 'Cardápio, QR Code e pedido guiado',
                    'description' => 'O consumidor entra por link ou QR da mesa, navega por categorias, escolhe adicionais e envia pedidos com menos dependência do atendimento manual.',
                ],
                [
                    'eyebrow' => 'Operação',
                    'title' => 'Comandas, cozinha, caixa e entrega em sincronia',
                    'description' => 'A equipe acompanha mesas, comandas, status de preparo, tickets, pagamentos e entregas sem quebrar o fluxo operacional do estabelecimento.',
                ],
                [
                    'eyebrow' => 'Gestão da empresa',
                    'title' => 'Catálogo, estoque, usuários e leitura gerencial',
                    'description' => 'O negócio administra produtos, adicionais, identidade visual, estoque, usuários internos, relatórios e configurações comerciais em um único ambiente.',
                ],
                [
                    'eyebrow' => 'Governança SaaS',
                    'title' => 'Empresas, planos, assinaturas e suporte',
                    'description' => 'O MesiMenu também opera como produto recorrente, com gestão de empresas assinantes, planos de assinatura, cobrança e acompanhamento institucional.',
                ],
            ],
            'problem_points' => [
                [
                    'title' => 'Vendas sem rastreabilidade',
                    'description' => 'Pedidos anotados manualmente geram retrabalho, erro operacional e nenhuma base real para decisão comercial.',
                ],
                [
                    'title' => 'Cobrança improvisada',
                    'description' => 'Sem um fluxo claro de assinatura, a empresa perde previsibilidade, atrasa recebimentos e mistura operação com financeiro.',
                ],
                [
                    'title' => 'Marketing sem conversão',
                    'description' => 'Tráfego pago ou orgânico não sustenta crescimento quando a página pública não explica valor, não prova confiança e não capta leads.',
                ],
                [
                    'title' => 'Equipe dependente de memória',
                    'description' => 'Quando processos vivem em conversas, a escala trava. O negócio fica lento, vulnerável e pouco replicável.',
                ],
                [
                    'title' => 'Fechamento de mesa com conflito',
                    'description' => 'Sem controle de comanda, consumo e status de pagamento, o fechamento vira discussão, demora no caixa e risco de cobrar errado ou deixar item para trás.',
                ],
                [
                    'title' => 'Financeiro sem conciliação real',
                    'description' => 'Quando recebimentos, comandas e formas de pagamento não conversam entre si, sobra caixa sem conferência, falha de registro e pouca clareza sobre o que realmente entrou.',
                ],
            ],
            'solutions' => [
                [
                    'eyebrow' => 'Operação',
                    'title' => 'Atendimento mais rápido e operação mais organizada',
                    'description' => 'O MesiMenu conecta mesas, comandas, pedidos e cozinha em um fluxo mais claro para reduzir falhas, acelerar o atendimento e melhorar a experiência do cliente.',
                ],
                [
                    'eyebrow' => 'Pagamento',
                    'title' => 'Fechamento de conta com mais controle e menos atrito',
                    'description' => 'Pagamentos, consumo e status da comanda ficam integrados para diminuir erro de cobrança, reduzir divergências no caixa e dar mais segurança no fechamento.',
                ],
                [
                    'eyebrow' => 'Gestão',
                    'title' => 'Mais padrão para a equipe e mais controle para a gestão',
                    'description' => 'Com processos registrados no sistema, a empresa deixa de depender de memória e improviso, ganha visibilidade da operação e cria base para crescer com mais consistência.',
                ],
                [
                    'eyebrow' => 'Crescimento',
                    'title' => 'Mais força comercial para atrair e converter novas empresas',
                    'description' => 'A página do MesiMenu, os planos e o fluxo de contato ajudam a apresentar melhor a solução, fortalecer a proposta de valor e transformar interesse em oportunidade real de assinatura.',
                ],
            ],
            'feature_groups' => $this->featureGroups(),
            'public_interactions' => $publishedInteractions,
            'plans' => $plans,
            'featured_plans' => $featuredPlans,
            'recommended_plans' => $recommendedPlans,
            'plans_stats' => [
                'total_active' => count($plans),
                'featured' => count($featuredPlans),
                'recommended' => count($recommendedPlans),
            ],
            'workflow' => [
                [
                    'step' => '01',
                    'title' => 'A empresa encontra uma página pensada para converter interesse em assinatura',
                    'description' => 'A página apresenta dores reais, proposta de valor, planos disponíveis e chamadas de ação para transformar visita em contato comercial.',
                ],
                [
                    'step' => '02',
                    'title' => 'O plano certo fica mais claro no momento da decisão',
                    'description' => 'O catálogo destaca os planos disponíveis, recomendados e mais estratégicos para facilitar a comparação e reduzir fricção na contratação.',
                ],
                [
                    'step' => '03',
                    'title' => 'A assinatura avança com pagamento simples e recorrente',
                    'description' => 'PIX e cartão entram como caminhos diretos para acelerar a contratação, sustentar a recorrência e dar mais previsibilidade de receita.',
                ],
                [
                    'step' => '04',
                    'title' => 'A operação ganha visibilidade, controle e continuidade',
                    'description' => 'Depois da entrada, a empresa acompanha a operação com mais leitura gerencial, acesso organizado, suporte e módulos alinhados ao plano contratado.',
                ],
            ],
            'blog_articles' => [
                [
                    'category' => 'SEO local',
                    'title' => 'Como atrair clientes no Google quando o processo de venda ainda é manual',
                    'excerpt' => 'Entenda por que página bonita não basta: sem oferta clara, prova e captura de lead, o tráfego não vira receita.',
                ],
                [
                    'category' => 'Gestão',
                    'title' => 'Os gargalos invisíveis de operar pedidos e cobranças fora de um fluxo centralizado',
                    'excerpt' => 'Erros de pedido, perda de histórico e inadimplência normalmente nascem no mesmo problema: falta de sistema e padrão.',
                ],
                [
                    'category' => 'Assinatura',
                    'title' => 'Mensal ou anual: como posicionar seu plano para vender mais sem desvalorizar o produto',
                    'excerpt' => 'Precificação não é tabela isolada. Ela precisa conversar com ativação, capacidade operacional e percepção de valor.',
                ],
            ],
            'faq' => $this->faqItems(),
        ];
    }

    private function buildSeo(): array
    {
        $canonical = app_url('/');
        $logoUrl = asset_url('/img/logo-mesimenu.png');
        $faq = [];
        foreach ($this->faqItems() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $faq[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        return [
            'title' => 'MesiMenu | Plataforma para empresas que querem organizar atendimento, pedidos e cobrança',
            'description' => 'MesiMenu para empresas que querem organizar atendimento, comandas, pagamentos e contratação recorrente com planos mensais ou anuais.',
            'keywords' => 'sistema para restaurante, mesimenu, comanda digital, qr code para mesas, sistema de pedidos, cobrança recorrente pix',
            'canonical' => $canonical,
            'robots' => 'index,follow,max-image-preview:large',
            'og_image' => $logoUrl,
            'structured_data' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'MesiMenu',
                    'url' => $canonical,
                    'logo' => $logoUrl,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => 'MesiMenu',
                    'url' => $canonical,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'SoftwareApplication',
                    'name' => 'MesiMenu',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'url' => $canonical,
                    'description' => 'MesiMenu para operação comercial, assinaturas, pagamentos via PIX e cartão e digitalização de vendas.',
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $faq,
                ],
            ],
        ];
    }

    private function navigation(): array
    {
        return [
            ['href' => '#sobre', 'label' => 'Sobre'],
            ['href' => '#problemas', 'label' => 'Problemas'],
            ['href' => '#solucoes', 'label' => 'Soluções'],
            ['href' => '#funcionalidades', 'label' => 'Funcionalidades'],
            ['href' => '#planos', 'label' => 'Planos'],
            ['href' => '#blog', 'label' => 'Blog'],
            ['href' => '#contato', 'label' => 'Contato'],
            ['href' => '#faq', 'label' => 'FAQ'],
        ];
    }

    private function faqItems(): array
    {
        return [
            [
                'question' => 'O MesiMenu atende empresas com mesas, comandas e pedidos no salão?',
                'answer' => 'Sim. O MesiMenu foi pensado para organizar mesas, comandas, pedidos, atendimento e fechamento em um fluxo mais claro para a empresa assinante.',
            ],
            [
                'question' => 'Depois da contratação, meus clientes conseguem acessar o cardápio pelo QR Code da mesa?',
                'answer' => 'Sim. Depois que a empresa entra na plataforma, os clientes do estabelecimento podem acessar o cardápio pelo QR Code e seguir a jornada com mais autonomia.',
            ],
            [
                'question' => 'O MesiMenu ajuda no fechamento da conta e no controle de pagamento da empresa?',
                'answer' => 'Sim. Consumo, comanda, formas de pagamento e fechamento ficam mais conectados para reduzir erro operacional e dar mais segurança no caixa da empresa.',
            ],
            [
                'question' => 'A plataforma trabalha com pagamento via PIX e cartão?',
                'answer' => 'Sim. A plataforma contempla PIX, cartão e cobrança recorrente conforme o ciclo escolhido pela empresa no momento da assinatura.',
            ],
            [
                'question' => 'Posso contratar o MesiMenu no plano mensal ou anual?',
                'answer' => 'Sim. O MesiMenu pode apresentar contratação mensal e anual para facilitar a escolha do formato mais adequado para a empresa.',
            ],
            [
                'question' => 'Como eu escolho o plano ideal para a minha empresa?',
                'answer' => 'A comparação entre os planos ajuda a entender qual estrutura faz mais sentido para o momento da sua operação. Se houver dúvida, o canal de contato comercial existe justamente para orientar essa decisão.',
            ],
            [
                'question' => 'O MesiMenu serve apenas para operação ou também ajuda a empresa a vender melhor?',
                'answer' => 'Os dois. A plataforma melhora atendimento, organização da operação, fechamento e a capacidade da empresa de atender melhor e vender com menos erro.',
            ],
            [
                'question' => 'Esta página pública é da minha empresa ou do próprio MesiMenu?',
                'answer' => 'Esta página pública é do próprio MesiMenu e foi feita para atrair novas empresas interessadas em contratar a plataforma. Ela não é uma página promocional das empresas assinantes.',
            ],
            [
                'question' => 'Se eu quiser entender melhor o plano ideal, posso falar com o comercial antes de assinar?',
                'answer' => 'Sim. A seção de contato existe exatamente para isso: abrir uma conversa comercial com mais contexto sobre operação, momento da empresa e plano de interesse antes da contratação.',
            ],
        ];
    }

    private function featureGroups(): array
    {
        return [
            [
                'step' => '01',
                'eyebrow' => 'QR Code na mesa',
                'title' => 'O cliente entra no fluxo certo sem depender do atendimento manual',
                'description' => 'A leitura do QR Code identifica a mesa e reduz o atrito logo no primeiro contato, deixando o início do atendimento mais rápido e mais confiável.',
                'image' => 'img/qrcode-celular.png',
                'image_alt' => 'Acesso por QR Code no celular com o MesiMenu',
                'result' => 'Menos erro na identificação da mesa e mais agilidade no início do pedido.',
                'items' => [
                    'Entrada direta pelo celular do cliente',
                    'Identificação da mesa com menos atrito',
                    'Jornada inicial mais rápida e organizada',
                ],
            ],
            [
                'step' => '02',
                'eyebrow' => 'Cardápio digital',
                'title' => 'A empresa apresenta melhor o cardápio e vende com mais clareza',
                'description' => 'Produtos, categorias e jornada de escolha ficam mais intuitivos no celular, ajudando o cliente a navegar melhor e aumentando a chance de conversão.',
                'image' => 'img/menu-celular.png',
                'image_alt' => 'Cardápio digital do MesiMenu no celular',
                'result' => 'Mais autonomia para o cliente e mais capacidade de venda no salão.',
                'items' => [
                    'Categorias mais organizadas',
                    'Navegação pensada para celular',
                    'Apresentação comercial mais forte do cardápio',
                ],
            ],
            [
                'step' => '03',
                'eyebrow' => 'Produtos e adicionais',
                'title' => 'Itens, adicionais e observações ficam mais precisos no pedido',
                'description' => 'A escolha de produtos e complementos passa a seguir um fluxo guiado, reduzindo ruído entre cliente, atendimento e cozinha.',
                'image' => 'img/produtos-celular.png',
                'image_alt' => 'Tela de produtos e adicionais do MesiMenu',
                'result' => 'Menos retrabalho operacional e mais precisão no que vai para produção.',
                'items' => [
                    'Escolha de itens com melhor orientação visual',
                    'Adicionais e observações no mesmo fluxo',
                    'Pedido mais completo antes do envio',
                ],
            ],
            [
                'step' => '04',
                'eyebrow' => 'Caixa e comanda',
                'title' => 'A equipe acompanha consumo, caixa e fechamento com mais controle',
                'description' => 'Mesa, comanda e movimentação financeira deixam de ficar soltas e passam a seguir um fluxo operacional mais legível para quem atende e para quem gere.',
                'image' => 'img/caixa-celular.png',
                'image_alt' => 'Controle de caixa e comandas do MesiMenu',
                'result' => 'Mais segurança na operação diária e menos dependência de memória da equipe.',
                'items' => [
                    'Abertura e leitura mais clara das comandas',
                    'Acompanhamento do consumo e do caixa',
                    'Rotina de fechamento com menos conflito',
                ],
            ],
            [
                'step' => '05',
                'eyebrow' => 'Pagamento integrado',
                'title' => 'O recebimento acontece com mais segurança e menos divergência',
                'description' => 'Pagamentos, status da comanda e fechamento passam a conversar entre si, diminuindo erro de cobrança e melhorando a leitura do que realmente foi recebido.',
                'image' => 'img/pagamento-celular.png',
                'image_alt' => 'Fluxo de pagamentos do MesiMenu',
                'result' => 'Menos atrito no fechamento e mais confiança no controle financeiro.',
                'items' => [
                    'PIX e cartão no mesmo ecossistema',
                    'Leitura mais clara do status de pagamento',
                    'Fechamento com menos erro e mais confiabilidade',
                ],
            ],
            [
                'step' => '06',
                'eyebrow' => 'Leitura gerencial',
                'title' => 'A gestão acompanha indicadores e decide com mais visibilidade',
                'description' => 'A empresa sai do improviso e passa a ter uma camada visual de acompanhamento para entender desempenho, gargalos e oportunidades de crescimento.',
                'image' => 'img/estatistica-celular.png',
                'image_alt' => 'Indicadores e estatísticas do MesiMenu',
                'result' => 'Mais visibilidade para ajustar operação, venda e crescimento com base real.',
                'items' => [
                    'Indicadores para leitura mais rápida da operação',
                    'Acompanhamento gerencial em ambiente visual',
                    'Base mais forte para decidir e evoluir',
                ],
            ],
        ];
    }

    private function normalizePlans(array $plans): array
    {
        $normalized = [];
        foreach ($plans as $plan) {
            if (!is_array($plan)) {
                continue;
            }

            $featuresJson = $plan['features_json'] ?? null;
            $pricing = $this->featureCatalog->pricingConfigFromJson($featuresJson);
            $featureLabels = $this->featureCatalog->enabledLabelsFromJson($featuresJson);
            $priceMonthly = $pricing['mensal'] !== null
                ? (float) $pricing['mensal']
                : (float) ($plan['price_monthly'] ?? 0);
            $priceYearly = $pricing['anual'] !== null
                ? (float) $pricing['anual']
                : ($plan['price_yearly'] !== null ? (float) $plan['price_yearly'] : null);
            $yearlyBasePrice = round($priceMonthly * 12, 2);
            $yearlyDiscountPercent = round((float) ($pricing['desconto_anual_percentual'] ?? 0), 2);
            $publicLanding = $this->featureCatalog->publicLandingConfigFromJson($featuresJson);
            $normalized[] = [
                'id' => (int) ($plan['id'] ?? 0),
                'name' => trim((string) ($plan['name'] ?? 'Plano')),
                'slug' => trim((string) ($plan['slug'] ?? '')),
                'description' => trim((string) ($plan['description'] ?? '')),
                'price_monthly' => $priceMonthly,
                'price_yearly' => $priceYearly,
                'price_yearly_base' => $yearlyBasePrice,
                'price_yearly_discount_percent' => $yearlyDiscountPercent,
                'max_users' => $plan['max_users'] !== null ? (int) $plan['max_users'] : null,
                'max_products' => $plan['max_products'] !== null ? (int) $plan['max_products'] : null,
                'max_tables' => $plan['max_tables'] !== null ? (int) $plan['max_tables'] : null,
                'feature_labels' => $featureLabels,
                'public_display_order' => $publicLanding['ordem_exibicao'] ?? null,
                'is_featured' => !empty($publicLanding['destaque']),
                'is_recommended' => !empty($publicLanding['recomendado']),
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftOrder = isset($left['public_display_order']) ? (int) $left['public_display_order'] : PHP_INT_MAX;
            $rightOrder = isset($right['public_display_order']) ? (int) $right['public_display_order'] : PHP_INT_MAX;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            $leftRecommended = !empty($left['is_recommended']) ? 0 : 1;
            $rightRecommended = !empty($right['is_recommended']) ? 0 : 1;
            if ($leftRecommended !== $rightRecommended) {
                return $leftRecommended <=> $rightRecommended;
            }

            $leftFeatured = !empty($left['is_featured']) ? 0 : 1;
            $rightFeatured = !empty($right['is_featured']) ? 0 : 1;

            if ($leftFeatured !== $rightFeatured) {
                return $leftFeatured <=> $rightFeatured;
            }

            $priceCompare = ((float) $left['price_monthly']) <=> ((float) $right['price_monthly']);
            if ($priceCompare !== 0) {
                return $priceCompare;
            }

            return ((int) $left['id']) <=> ((int) $right['id']);
        });

        return $normalized;
    }
}
