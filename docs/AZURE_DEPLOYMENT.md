# EduPulse Azure Cloud Deployment Guide

This guide documents building, publishing, and running EduPulse on Azure Container Registry (ACR), Azure Container Apps (ACA), Azure Blob Storage, Azure Database for MySQL Flexible Server, and Azure Application Insights.

> **Important**: No paid Azure resources are provisioned by this repository automatically. Review SKU costs before creating resources in your subscription.

## 1. Azure Container Registry (ACR) Build, Tag & Push (Phase 4)
Never bake `.env`, `local.env.php`, or connection strings into the container image (`.dockerignore` blocks them automatically).

```bash
ACR_NAME="<your_acr_name>"
IMAGE_TAG="$(git rev-parse --short HEAD)"

# 1. Authenticate to ACR
az acr login --name "$ACR_NAME"

# 2. Reproducible build with immutable commit SHA + latest tags
docker build \
  -t "${ACR_NAME}.azurecr.io/edupulse-app:${IMAGE_TAG}" \
  -t "${ACR_NAME}.azurecr.io/edupulse-app:latest" \
  .

# 3. Push tagged images to ACR
docker push "${ACR_NAME}.azurecr.io/edupulse-app:${IMAGE_TAG}"
docker push "${ACR_NAME}.azurecr.io/edupulse-app:latest"
```

## 2. Azure Blob Storage Configuration (Phase 2)
Create a private blob container (`edupulse-files`) in an Azure StorageV2 account (`Standard_LRS`). Keep container public access **Disabled**. EduPulse serves files exclusively after PHP session & RBAC ownership verification via `backend/api/download_document.php`, or temporary signed SAS URLs when configured.

Required environment variables:
- `STORAGE_DRIVER=azure`
- `AZURE_STORAGE_CONNECTION_STRING` *(store as Container App secret)*
- `AZURE_STORAGE_CONTAINER=edupulse-files`

## 3. Azure Container Apps Deployment (Phase 5)
A declarative specification is provided in `infra/containerapp.yaml` with:
- External HTTPS ingress (`allowInsecure: false`, port `80`)
- Liveness and readiness HTTP probes targeting `/backend/api/health.php`
- Secret references (`db-password`, `storage-conn-str`, `appinsights-conn-str`)

```bash
# Deploy or update from declarative manifest (after filling placeholders or via CI/CD)
az containerapp update \
  --name edupulse-app \
  --resource-group "<resource_group>" \
  --image "${ACR_NAME}.azurecr.io/edupulse-app:${IMAGE_TAG}"
```

Post-deploy verification:
- Check `https://<app-fqdn>/backend/api/health.php` returns HTTP `200` with `"status": "healthy"`.
- Verify student & admin login, analytics dashboard, and uploading an academic document on `profile.html`.

## 4. Azure Application Insights (Phase 6)
Set `APPINSIGHTS_CONNECTION_STRING` in Container App secrets/environment variables.
- Request timings, HTTP response status codes (`5xx` failures), uncaught exceptions, and fatal errors are emitted to Application Insights `/v2/track`.
- `backend/includes/telemetry.php` automatically scrubs passwords, keys, tokens, emails, and connection strings before transmission.
- For local verification without Azure credits, set `APP_TELEMETRY_LOG=/tmp/edupulse_telemetry.ndjson`.

## 5. GitHub Actions CI/CD Secrets & Variables (Phase 7)
Configure these in **GitHub Repository Settings -> Secrets and variables -> Actions** to enable automated CD on `main`:

**Variables (`vars`)**:
- `AZURE_DEPLOY_ENABLED`: set to `true` when ready to deploy automatically
- `ACR_NAME`: ACR registry name (without `.azurecr.io`)
- `AZURE_CONTAINER_APP_NAME`: Container App name (e.g., `edupulse-app`)
- `AZURE_RESOURCE_GROUP`: Target Azure resource group name

**Secrets (`secrets`)** *(using OpenID Connect / Workload Identity Federation — no static client secrets needed)*:
- `AZURE_CLIENT_ID`
- `AZURE_TENANT_ID`
- `AZURE_SUBSCRIPTION_ID`

