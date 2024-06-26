# inpher

[PHP](https://www.php.net/) console application to automate work with structured data using [OpenAI](https://openai.com/) (or LLMs to be specific).

## Usage

Run the [Docker](https://www.docker.com/) image in any folder as below:

```shell
# supply OPENAI_API_KEY as run args
docker run -it --rm \
  -e OPENAI_API_KEY=sk-****** \
  -v $PWD:/workspace \
  ghcr.io/vaibhavpandeyvpz/inpher
  
# read OPENAI_API_KEY from a .env file
docker run -it --rm \
  --env-file .env \
  -v $PWD:/workspace \
  ghcr.io/vaibhavpandeyvpz/inpher
```

The application will then interactively prompt you for input.

## License

See the [LICENSE](LICENSE) file.
