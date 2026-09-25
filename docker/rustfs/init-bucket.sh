#!/bin/sh
# Creates the Drupal files bucket (idempotent) and allows anonymous reads so
# browsers can load public files straight from the S3 endpoint.
set -eu

if aws s3api head-bucket --bucket "$S3_BUCKET" 2>/dev/null; then
  echo "Bucket '$S3_BUCKET' already exists."
else
  echo "Creating bucket '$S3_BUCKET'..."
  aws s3api create-bucket --bucket "$S3_BUCKET"
fi

cat > /tmp/policy.json <<POLICY
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicRead",
      "Effect": "Allow",
      "Principal": "*",
      "Action": ["s3:GetObject"],
      "Resource": ["arn:aws:s3:::${S3_BUCKET}/*"]
    }
  ]
}
POLICY
aws s3api put-bucket-policy --bucket "$S3_BUCKET" --policy file:///tmp/policy.json
echo "Bucket '$S3_BUCKET' is ready (public read)."
