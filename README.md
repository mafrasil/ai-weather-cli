# AI Weather Chatbot

A simple CLI-based AI weather chatbot built with Laravel and Prism PHP.

## Features

-   **AI-Powered Conversations**: Chat naturally about weather using Prism PHP
-   **Real-time Weather Data**: Current conditions and forecasts via Open-Meteo API
-   **Smart Memory**: AI remembers your location, preferences, and context across sessions
-   **Streaming Responses**: Real-time response generation with visual feedback
-   **Auto-Save Conversations**: Automatic conversation summarization on exit
-   **Tool Integration**: AI can use weather tools and memory management tools

## Get Started

### Prerequisites

-   PHP 8.1 or higher
-   Composer
-   SQLite support (usually included with PHP)

### Installation

1. Clone the repository:

```bash
git clone https://github.com/mafrasil/ai-weather-cli.git
cd ai-weather-cli
```

2. Install dependencies:

```bash
composer install
```

3. Set up environment:

```bash
cp .env.example .env
php artisan key:generate
```

4. Configure API keys in `.env`:

```bash
PRISM_PROVIDER=anthropic
PRISM_PROVIDER_MODEL=claude-3-5-haiku-20241022

ANTHROPIC_API_KEY=sk-ant-xxx
```

See `config/prism.php` for other providers and models.

5. Set up database:

```bash
php artisan migrate
```

## Usage

### Basic Usage

Start a weather chat session:

```bash
php artisan weather:chat --user=john --memories --stream
```

### Command Options

| Option              | Description                                | Default           |
| ------------------- | ------------------------------------------ | ----------------- |
| `--user=NAME`       | User identifier for personalized sessions  | Prompts for name  |
| `--memories`        | Enable memory persistence across sessions  | Disabled          |
| `--stream`          | Enable real-time streaming responses       | Disabled          |
| `--summarize`       | Show summary of stored user context        | -                 |
| `--no-save-on-exit` | Disable conversation summarization on exit | Auto-save enabled |

-   **Exit**: Type `/exit` to quit

## Architecture

### Technology Stack

-   **Laravel**: CLI command support and database management
-   **Prism PHP**: AI tool orchestration and LLM integration
-   **Open-Meteo API**: Free weather data (no API key required)
-   **SQLite**: Lightweight database for user memory

### Memory System

The chatbot uses a simple, intelligent memory system:

-   **Semantic Keys**: Information stored with descriptive keys like `home_location`, `preferred_units`
-   **AI-Controlled**: The AI decides what's worth remembering
-   **Auto-Summarization**: Conversations are automatically summarized on exit

### Key Design Decisions

-   **SQLite**: Zero configuration, perfect for CLI apps
-   **Open-Meteo**: No API key required, excellent data quality
-   **Simplified Memory**: Single key-value pairs instead of complex hierarchies
-   **Real-time Streaming**: Better user experience with immediate feedback

## Testing

Run the test suite:

```bash
php artisan test
```

The project includes comprehensive tests:

-   Unit tests for core services
-   Integration tests for AI processing
-   Feature tests for CLI commands
-   Prism-specific tests for AI framework integration

### Major Design Decisions & Trade-offs

#### 1. Memory Strategy: Simplified Schema vs Complex Type System

**Decision**: Simplified semantic keys over type-based categorization
**Rationale**:

-   **Reduced Complexity**: Single key-value pairs instead of type/key/value hierarchy
-   **Self-Documenting**: Keys like `home_location` are immediately understandable
-   **Flexible Grouping**: Visual categorization by pattern matching vs rigid database constraints
-   **Simpler Queries**: Direct key lookups instead of compound type+key queries

**Trade-off**: Less rigid categorization, relies on naming conventions
**Alternative Considered**: Type-based system (more complex), tag-based system (overkill)

#### 2. Testing Strategy: Full Mocking vs Integration Tests

**Decision**: Comprehensive mocking with Prism fakes
**Rationale**:

-   **Speed**: Tests run quickly without real API calls
-   **Reliability**: No dependency on external services
-   **Cost**: No API usage costs during testing
-   **Control**: Predictable responses for edge case testing

**Trade-off**: Don't test actual API integration, potential for mismatch with real APIs
**Alternative Considered**: Integration tests (slow, expensive, unreliable)

### Features Chosen NOT to Implement (And Why)

#### 1. Automatic Location Detection via IP Geolocation

**Why Not**:

-   **Privacy Concerns**: IP geolocation can be inaccurate and feels invasive
-   **Reliability**: VPNs and proxies make IP location unreliable

#### 2. Multiple Weather Providers with Fallback

**Why Not**:

-   **Complexity**: Would require provider abstraction layer and failover logic
-   **Diminishing Returns**: Open-Meteo is reliable enough for this scope

## Future Improvements

### Immediate

1. **Caching Layer**: Redis for API response caching to improve performance
2. **Enhanced Error Recovery**: More sophisticated retry mechanisms for API failures
3. **Background Processing**: Queue system for expensive operations

### Features

1. **Weather Alerts**: System-level notifications for severe weather
2. **Historical Analysis**: Basic weather trend analysis and comparisons
3. **Configuration Management**: User-customizable response formats and preferences
4. **Rich Weather Visualizations (ASCII Art)**: More engaging and informative responses
