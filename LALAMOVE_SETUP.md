# Lalamove API Integration Setup

## Environment Configuration

Add the following variables to your `.env` file:

```env
# Lalamove API Configuration
LALAMOVE_API_KEY=pk_test_YOUR_SANDBOX_KEY
LALAMOVE_API_SECRET=sk_test_YOUR_SANDBOX_SECRET
LALAMOVE_ENVIRONMENT=sandbox
LALAMOVE_MARKET=PH
LALAMOVE_WEBHOOK_URL=https://your-domain.com/api/lalamove/webhook

# Google Maps API (for geocoding addresses to coordinates)
GOOGLE_MAPS_API_KEY=YOUR_GOOGLE_MAPS_API_KEY
```

## Getting API Credentials

### Lalamove API Credentials

1. **Register for Sandbox Account**:
   - Go to [Lalamove Partner Portal](https://partner.lalamove.com/)
   - Create a sandbox account
   - Navigate to Developers tab

2. **Get API Keys**:
   - Copy your `pk_test_` (API Key) and `sk_test_` (API Secret)
   - Update the `.env` file with your credentials

3. **Production Setup** (when ready):
   - Top up your Lalamove wallet
   - Switch to production environment
   - Update credentials to `pk_prod_` and `sk_prod_` format

### Google Maps API Key (for Geocoding)

1. **Get Google Maps API Key**:
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select existing one
   - Enable the "Geocoding API"
   - Create credentials (API Key)
   - Restrict the API key to Geocoding API only

2. **Add to Environment**:
   - Add `GOOGLE_MAPS_API_KEY` to your `.env` file
   - Note: Without this, the system will use default coordinates for Pampanga cities

## Webhook Configuration

1. **Set Webhook URL**:
   - Update `LALAMOVE_WEBHOOK_URL` to your production domain
   - Example: `https://onlyfarms.com/api/lalamove/webhook`

2. **Configure in Partner Portal**:
   - Go to Developers > Webhook section
   - Set webhook URL and version
   - Test webhook connectivity

## Database Migration

Run the following command to create the Lalamove tables:

```bash
php artisan migrate
```

This will create:
- `lalamove_deliveries` table for tracking delivery details
- Add `lalamove_delivery_fee` and `lalamove_order_id` columns to `orders` table

## Testing

1. **Sandbox Testing**:
   - Use sandbox credentials for development
   - Test quotation and order placement
   - Verify webhook responses

2. **Production Rollout**:
   - Update to production credentials
   - Monitor first few orders closely
   - Set up error alerting

## Security Notes

- Never commit API keys to version control
- Use environment variables for all credentials
- Validate webhook signatures
- Implement rate limiting on API endpoints
