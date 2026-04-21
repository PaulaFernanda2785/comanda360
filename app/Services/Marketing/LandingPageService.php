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
        private readonly PlanFeatureCatalogService $featureCatalog = new PlanFeatureCatalogService()
    ) {}

    public function build(): array
    {
        try {
            $companySummary = $this->companies->summary();
            $subscriptionSummary = $this->subscriptions->summary();
            $plans = $this->normalizePlans($this->plans->listActiveForPublicCatalog());
        } catch (Throwable) {
            $companySummary = [];
            $subscriptionSummary = [];
            $plans = [];
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
                    'label' => 'operacoes em andamento',
                ],
                [
                    'value' => (string) ((int) ($subscriptionSummary['active_subscriptions'] ?? 0) + (int) ($subscriptionSummary['trial_subscriptions'] ?? 0)),
                    'label' => 'assinaturas monitoradas',
                ],
                [
                    'value' => 'PIX + cartao',
                    'label' => 'cobranca mensal e anual',
                ],
                [
                    'value' => 'SEO ready',
                    'label' => 'estrutura pronta para indexacao',
                ],
            ],
            'problem_points' => [
                [
                    'title' => 'Vendas sem rastreabilidade',
                    'description' => 'Pedidos anotados manualmente geram retrabalho, erro operacional e nenhuma base real para decisao comercial.',
                ],
                [
                    'title' => 'Cobranca improvisada',
                    'description' => 'Sem um fluxo claro de assinatura, a empresa perde previsibilidade, atrasa recebimentos e mistura operacao com financeiro.',
                ],
                [
                    'title' => 'Marketing sem conversao',
                    'description' => 'Trafego pago ou organico nao sustenta crescimento quando a pagina publica nao explica valor, nao prova confianca e nao capta leads.',
                ],
                [
                    'title' => 'Equipe dependente de memoria',
                    'description' => 'Quando processos vivem em conversas, a escala trava. O negocio fica lento, vulneravel e pouco replicavel.',
                ],
            ],
            'solutions' => [
                [
                    'eyebrow' => 'Operacao',
                    'title' => 'Fluxo comercial e operacional no mesmo SaaS',
                    'description' => 'Da captura de interesse ate o uso diario da plataforma, o Comanda360 conecta login, plano, assinatura, cobranca e operacao sem quebrar a jornada.',
                ],
                [
                    'eyebrow' => 'Receita recorrente',
                    'title' => 'Assinaturas com pagamento mensal ou anual',
                    'description' => 'A plataforma suporta contratos recorrentes, PIX, cartao e leitura do status da cobranca para proteger receita e reduzir inadimplencia.',
                ],
                [
                    'eyebrow' => 'Crescimento',
                    'title' => 'Pagina publica pensada para SEO e conversao',
                    'description' => 'Estrutura semantica, conteudo comercial, FAQ, blocos de prova e CTA forte para melhorar indexacao e transformar visitas em contato qualificado.',
                ],
            ],
            'feature_groups' => $this->featureGroups(),
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
                    'title' => 'Empresa entra pelo canal publico',
                    'description' => 'A landing apresenta problema, proposta de valor, planos ativos e CTA de contato ou acesso.',
                ],
                [
                    'step' => '02',
                    'title' => 'Plano escolhido e assinatura registrada',
                    'description' => 'O catalogo comercial respeita os planos ativos, os destaques e os recomendados definidos no cadastro interno.',
                ],
                [
                    'step' => '03',
                    'title' => 'Pagamento recorrente sem friccao',
                    'description' => 'PIX para rapidez operacional e cartao para continuidade de cobranca, com ciclos mensal e anual.',
                ],
                [
                    'step' => '04',
                    'title' => 'Operacao acompanhada com visibilidade',
                    'description' => 'Dashboard, suporte, controle de acesso e modulos do produto ficam alinhados ao plano contratado.',
                ],
            ],
            'blog_articles' => [
                [
                    'category' => 'SEO local',
                    'title' => 'Como atrair clientes no Google quando o processo de venda ainda e manual',
                    'excerpt' => 'Entenda por que pagina bonita nao basta: sem oferta clara, prova e captura de lead, o trafego nao vira receita.',
                ],
                [
                    'category' => 'Gestao',
                    'title' => 'Os gargalos invisiveis de operar pedidos e cobrancas fora de um fluxo centralizado',
                    'excerpt' => 'Erros de pedido, perda de historico e inadimplencia normalmente nascem no mesmo problema: falta de sistema e padrao.',
                ],
                [
                    'category' => 'Assinatura',
                    'title' => 'Mensal ou anual: como posicionar seu plano para vender mais sem desvalorizar o produto',
                    'excerpt' => 'Precificacao nao e tabela isolada. Ela precisa conversar com onboarding, capacidade operacional e percepcao de valor.',
                ],
            ],
            'faq' => [
                [
                    'question' => 'Os planos exibidos na pagina publica sao automaticos?',
                    'answer' => 'Sim. A landing publica lista apenas planos ativos cadastrados no painel SaaS e aplica os marcadores de destaque e recomendado definidos no catalogo.',
                ],
                [
                    'question' => 'A plataforma trabalha com pagamento via PIX e cartao?',
                    'answer' => 'Sim. O fluxo comercial e financeiro contempla PIX, cartao e cobranca recorrente conforme o ciclo escolhido.',
                ],
                [
                    'question' => 'Posso vender mensal e anual ao mesmo tempo?',
                    'answer' => 'Sim. Cada plano pode apresentar precificacao mensal e anual, com comunicacao clara na pagina publica.',
                ],
                [
                    'question' => 'A pagina foi estruturada para SEO?',
                    'answer' => 'Sim. A pagina entrega hierarquia semantica, conteudo comercial indexavel, FAQ e dados estruturados para apoiar a indexacao.',
                ],
            ],
        ];
    }

    private function buildSeo(): array
    {
        $canonical = app_url('/');
        $logoUrl = asset_url('/img/logo-comanda360.png');

        $faq = [
            [
                '@type' => 'Question',
                'name' => 'Os planos exibidos na pagina publica sao automaticos?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'A pagina publica lista apenas planos ativos cadastrados no painel SaaS e aplica os marcadores de destaque e recomendado definidos no catalogo.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'A plataforma trabalha com pagamento via PIX e cartao?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Sim. O fluxo contempla PIX, cartao e cobranca recorrente com ciclos mensal e anual.',
                ],
            ],
        ];

        return [
            'title' => 'Comanda360 | SaaS para vendas, operacao e assinaturas com SEO e cobranca recorrente',
            'description' => 'Sistema SaaS para digitalizar vendas, comandas, operacao e cobranca recorrente com planos mensais ou anuais, PIX, cartao e pagina publica preparada para SEO.',
            'keywords' => 'sistema para restaurante, saas para delivery, comanda digital, cobranca recorrente pix, pagina de login seo, software de vendas',
            'canonical' => $canonical,
            'robots' => 'index,follow,max-image-preview:large',
            'og_image' => $logoUrl,
            'structured_data' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'Comanda360',
                    'url' => $canonical,
                    'logo' => $logoUrl,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => 'Comanda360',
                    'url' => $canonical,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'SoftwareApplication',
                    'name' => 'Comanda360',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'url' => $canonical,
                    'description' => 'SaaS para operacao comercial, assinaturas, pagamentos via PIX e cartao e digitalizacao de vendas.',
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
            ['href' => '#solucoes', 'label' => 'Solucoes'],
            ['href' => '#funcionalidades', 'label' => 'Funcionalidades'],
            ['href' => '/login', 'label' => 'Acesso'],
            ['href' => '#blog', 'label' => 'Blog'],
            ['href' => '#contato', 'label' => 'Contato'],
            ['href' => '#planos', 'label' => 'Planos'],
        ];
    }

    private function featureGroups(): array
    {
        return [
            [
                'title' => 'Atracao e conversao',
                'items' => [
                    'Pagina publica com CTA estrategico',
                    'Catalogo de planos ativos e destacados',
                    'Formulario de contato para lead qualificado',
                    'Blocos de SEO com FAQ e conteudo indexavel',
                ],
            ],
            [
                'title' => 'Gestao do cliente SaaS',
                'items' => [
                    'Cadastro de empresas com contexto comercial',
                    'Assinaturas vinculadas ao plano contratado',
                    'Controle de status, trial e renovacao',
                    'Historico de cobrancas e acompanhamento financeiro',
                ],
            ],
            [
                'title' => 'Financeiro recorrente',
                'items' => [
                    'Pagamento via PIX',
                    'Cartao para cobranca recorrente',
                    'Ciclos mensal e anual',
                    'Leitura de status de pagamento e sincronizacao de gateway',
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
