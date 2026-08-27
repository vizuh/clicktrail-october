English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/october-clicktrail**

Leve o contexto de aquisição observado aos envios de formulários configurados
no October CMS.

</div>

[![CI](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/october-clicktrail)](https://packagist.org/packages/vizuh/october-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Índice

- [Por quê](#por-quê)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Componentes](#componentes)
- [Captura de atribuição](#captura-de-atribuição)
- [Configurações](#configurações)
- [Consentimento](#consentimento)
- [Entrega](#entrega)
- [Como é diferente](#como-é-diferente)
- [Testes](#testes)
- [Licença](#licença)

## Por quê

O ClickTrail anexa o contexto armazenado de primeiro e último toque aos envios
de formulários configurados no October. Ele não determina qual toque de
marketing causou um envio. O núcleo compartilhado
[`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) calcula o payload
com regras de merge validadas por golden fixtures.

Requer October CMS 3 (base Laravel 10), PHP 8.1+ e `clicktrail/php-sdk` (^0.1@dev).

## Instalação

A raiz deste repositório **é** a pasta do plugin. Clone ou copie para um projeto October:

```bash
cd <october-project>/plugins
mkdir -p vizuh && cd vizuh
git clone https://github.com/vizuh/clicktrail-october clicktrail
php artisan october:up
```

Depois ative **ClickTrail** em Configurações e informe seu Site ID.

## Início rápido

Adicione o tracker ao `<head>` do layout e os campos ocultos a qualquer formulário October:

```twig
{# layouts/default.htm; dentro do <head> #}
{% component 'clickTrailTracker' %}

{# qualquer formulário October #}
<form data-request="onSubmit">
    {% component 'attributionHidden' %}
    ...
</form>
```

Um visitante chega por um anúncio de pesquisa paga, navega e envia o formulário. O POST passa a carregar todo o contexto do primeiro toque:

```text
ct_utm_source=google          ← primeiro toque, imutável
ct_utm_medium=cpc             ← mesmo após visitas diretas posteriores
ct_gclid=EAIaIQobChMI...      ← click ID capturado com consentimento de publicidade
ct_landing_page=https://example.com/promo
ct_initial_referrer=https://www.google.com/
ct_consent_state=granted
```

Qualquer visita direta seguinte não muda nada; o primeiro toque permanece, o último toque armazenado persiste. Essa lei de mesclagem vive no SDK compartilhado, testada, não prometida.

## Componentes

### `clickTrailTracker`; loader first-party

```twig
{% component 'clickTrailTracker' %}
```

Renderiza uma tag de script para o loader ClickTrail e o Site ID configurados.
O host controla a escolha do endpoint; este component não injeta tags
adicionais.

### `attributionHidden`; campos de atribuição em qualquer formulário

```twig
{% component 'attributionHidden' %}
```

Renderiza um input oculto por atributo coletado, espelhando a lista de variáveis de atribuição do GTM: IDs de visitante/sessão/evento, `utm_*`, click IDs de anúncios (`gclid`, `fbclid`, `msclkid`, `ttclid`, ...), página de destino, referrer inicial e estado de consentimento. Os valores vêm do par mesclado guardado na sessão; os valores dos campos nunca decidem a lógica de atribuição.

## Captura de atribuição

Exibições de página e requisições AJAX do framework October são observadas automaticamente (`cms.page.beforeDisplay` / `ajax.beforeRun`). Cada requisição é lida como um toque e mesclada no estado da sessão pelo `TouchMerger` compartilhado. Nenhum código extra necessário.

## Configurações

Todas as opções ficam em Configurações → ClickTrail:

| Opção | Padrão | Finalidade |
|---|---|---|
| Site ID | vazio | Identifica este site para sua conta ClickTrail |
| Endpoint da API | vazio | Para onde os payloads são enviados; também serve o loader |
| Classe resolvedora de consentimento | vazia | Implementação customizada de `ConsentResolverInterface` que retorna o snapshot normalizado; vazia = todos os sinais "unknown" |
| Persistência exige `analytics_storage` | ligado | Não armazenar nada sem consentimento de analytics concedido |
| Click IDs exigem `advertising_storage` | ligado | Remover gclid/fbclid/... do armazenamento sem consentimento de publicidade |
| Encaminhar dados de lead com hash (`ad_user_data`) | desligado | Portão extra para encaminhamento de leads com hash; ainda exige `ad_user_data` concedido |
| Proxy first-party | desligado | Servir o loader ClickTrail pelo seu próprio domínio |

## Consentimento

O ClickTrail não substitui sua plataforma de consentimento; ele a obedece. O contrato normalizado de consentimento (capacidades, formato do snapshot, matriz de comportamento) está em [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Provedor: implemente `Vizuh\ClickTrail\Classes\Consent\ConsentResolverInterface` (retorna o `ClickTrail\Consent\ConsentSnapshot` atual) e registre em Configurações → Privacidade → Consent resolver class. Adaptadores reais de CMP estão adiados; o plugin WordPress lê a WP Consent API diretamente.
- Com consentimento desconhecido: **não armazenar nem enviar**. Ações suprimidas são registradas com `suppressionReason()` nos diagnósticos.
- O snapshot resolvido é persistido junto ao estado de atribuição e viaja com cada envio (chave `consent` em cada payload).

## Entrega

Payloads canônicos são serializados contra o par de atribuição armazenado pelo SDK compartilhado (com `schema_version`, chaves `attribution.*` pontilhadas). Um hook agendado de descarga da fila já está registrado; o transporte de eventos persistidos entra assim que o parity gate do clicktrail-php passar.

## Como é diferente

| Configuração típica de analytics | ClickTrail para October |
|---|---|
| Sessões e páginas num dashboard | Campanha, palavra-chave, click ID e landing page **no registro de cada envio** |
| Tags client-side mantidas por você | Dois componentes Twig, um script first-party |
| Lógica de atribuição duplicada por plataforma | Um motor determinístico, testado por fixtures no WordPress, GTM e integrações PHP |

## Testes

O CI no GitHub Actions faz lint de todos os arquivos PHP a cada push ([workflow](https://github.com/vizuh/clicktrail-october/blob/main/.github/workflows/ci.yml)).

## Licença

MIT; Copyright (c) 2026 Vizuh OÜ. Consulte [LICENSE](LICENSE).
