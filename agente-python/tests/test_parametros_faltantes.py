"""Pergunta ao usuário quando falta um parâmetro de função.

Rodar de dentro do contêiner do agente:
    python -m unittest discover -s tests -t .
"""

import unittest
from unittest.mock import patch

from app.funcoes import analisar_parametros, validar_parametros
from app.graph import nodes

LICENCA = {
    "id": 8,
    "codigo": "licenca.vagas.ler",
    "nome": "Consultar as vagas de uma licença",
    "descricao": "Lê as vagas de uma licença.",
    "tipo": "consulta",
    "parametros": [
        {"nome": "licenca_nome", "tipo": "string", "obrigatorio": True, "descricao": "Nome da licença de software."},
    ],
}

FECHAMENTO = {
    "id": 2,
    "codigo": "entidade.fechamento_automatico.alterar",
    "nome": "Alterar o fechamento automático",
    "descricao": "Altera o prazo.",
    "tipo": "alteracao",
    "parametros": [
        {"nome": "entidade_id", "tipo": "integer", "obrigatorio": True, "descricao": "Entidade a alterar."},
        {"nome": "dias", "tipo": "integer", "obrigatorio": True, "descricao": "Novo prazo em dias."},
    ],
}


class AnalisarParametrosTest(unittest.TestCase):
    def test_obrigatorio_ausente_vira_problema(self):
        validos, problemas = analisar_parametros(LICENCA, {}, {})

        self.assertEqual(validos, {})
        self.assertEqual([(p["nome"], p["motivo"]) for p in problemas], [("licenca_nome", "ausente")])
        self.assertEqual(problemas[0]["descricao"], "Nome da licença de software.")

    def test_valor_que_nao_converte_vira_invalido(self):
        validos, problemas = analisar_parametros(FECHAMENTO, {"entidade_id": 0, "dias": "muitos"}, {})

        self.assertEqual(validos, {"entidade_id": 0})
        self.assertEqual([(p["nome"], p["motivo"]) for p in problemas], [("dias", "invalido")])

    def test_entidade_assume_a_do_usuario(self):
        validos, problemas = analisar_parametros(FECHAMENTO, {"dias": 3}, {"entidade_id": 1})

        self.assertEqual(validos, {"entidade_id": 1, "dias": 3})
        self.assertEqual(problemas, [])

    def test_validar_parametros_continua_devolvendo_none_com_problema(self):
        self.assertIsNone(validar_parametros(LICENCA, {}, {}))
        self.assertEqual(validar_parametros(LICENCA, {"licenca_nome": "Office"}, {}), {"licenca_nome": "Office"})


class DecidirTest(unittest.TestCase):
    def _estado(self, **extra):
        return {"question": "A opção de adicionar na licença não aparece.", "regras": [], "contexto_erp": {}, **extra}

    def test_funcao_escolhida_sem_o_parametro_gera_faltante(self):
        decisao = {"function_id": 8, "parametros": {}, "motivo": "confere as vagas"}

        with patch.object(nodes, "decidir_funcao", return_value=decisao):
            escolha, faltante = nodes._decidir(self._estado(), [LICENCA])

        self.assertIsNone(escolha)
        self.assertEqual(faltante["function_id"], 8)
        self.assertEqual(faltante["tipo"], "consulta")
        self.assertEqual(faltante["parametros"][0]["nome"], "licenca_nome")

    def test_parametros_completos_escolhem_a_funcao(self):
        decisao = {"function_id": 8, "parametros": {"licenca_nome": "Office"}, "motivo": None}

        with patch.object(nodes, "decidir_funcao", return_value=decisao):
            escolha, faltante = nodes._decidir(self._estado(), [LICENCA])

        self.assertIsNone(faltante)
        self.assertEqual(escolha[0]["id"], 8)
        self.assertEqual(escolha[1], {"licenca_nome": "Office"})

    def test_nao_pergunta_de_novo_numa_resposta_a_esclarecimento(self):
        decisao = {"function_id": 8, "parametros": {}, "motivo": None}

        with patch.object(nodes, "decidir_funcao", return_value=decisao):
            escolha, faltante = nodes._decidir(self._estado(respondendo_esclarecimento=True), [LICENCA])

        self.assertEqual((escolha, faltante), (None, None))

    def test_funcao_fora_das_opcoes_e_descartada_sem_perguntar(self):
        decisao = {"function_id": 999, "parametros": {}, "motivo": None}

        with patch.object(nodes, "decidir_funcao", return_value=decisao):
            self.assertEqual(nodes._decidir(self._estado(), [LICENCA]), (None, None))

    def test_modelo_diz_nenhuma_nao_gera_pergunta(self):
        with patch.object(nodes, "decidir_funcao", return_value=None):
            self.assertEqual(nodes._decidir(self._estado(), [LICENCA]), (None, None))


class NosTest(unittest.TestCase):
    def _estado_com_candidata(self):
        return {
            "question": "A opção de adicionar na licença não aparece.",
            "contexto_erp": {"usuario_login": "glpi"},
            "funcoes": [LICENCA],
            "regras": [{"content": "Sintoma...", "function_ids": [8]}],
        }

    def test_selecionar_funcao_guarda_o_que_faltou_e_nao_pede_execucao(self):
        decisao = {"function_id": 8, "parametros": {}, "motivo": None}

        with patch.object(nodes, "decidir_funcao", return_value=decisao):
            novo = nodes.node_selecionar_funcao(self._estado_com_candidata())

        self.assertIsNone(novo["acao"])
        self.assertEqual(novo["parametro_faltante"]["codigo"], "licenca.vagas.ler")
        self.assertEqual(nodes.rota_funcao(novo), "formular_resposta")

    def _formular(self, texto_do_modelo, marcou_pergunta):
        estado = {
            "question": "x",
            "diagnostico": {"fontes": [{"knowledge_id": 1, "modulo": "Ativos", "trecho": "t", "score": 0.7}]},
            "modulos": [],
            "parametro_faltante": {
                "function_id": 8, "codigo": "licenca.vagas.ler", "funcao": "Consultar as vagas", "tipo": "consulta",
                "parametros": [{"nome": "licenca_nome", "tipo": "string", "descricao": "Nome da licença de software.", "motivo": "ausente"}],
            },
        }

        with patch.object(nodes, "formulate_response", return_value=(texto_do_modelo, True, marcou_pergunta)):
            return nodes.node_formular_resposta(estado)

    def test_pedido_do_modelo_marcado_como_pergunta_e_mantido_mesmo_sem_interrogacao(self):
        texto = "Para eu verificar no sistema, preciso que você me diga o nome da licença."

        novo = self._formular(texto, marcou_pergunta=True)

        self.assertEqual(novo["resposta_texto"], texto)
        self.assertTrue(novo["aguardando_esclarecimento"])
        self.assertIsNone(novo["modulo_recurso"])

    def test_resposta_completa_sem_marcador_e_trocada_pela_pergunta_padrao(self):
        novo = self._formular("Aqui vai todo o passo a passo da licença.", marcou_pergunta=False)

        self.assertIn("preciso saber: nome da licença de software", novo["resposta_texto"])
        self.assertTrue(novo["resposta_texto"].endswith("Pode me informar?"))
        self.assertTrue(novo["aguardando_esclarecimento"])

    def test_pergunta_padrao_para_valor_invalido_e_para_alteracao(self):
        faltante = {
            "tipo": "alteracao",
            "parametros": [{"nome": "dias", "descricao": "Novo prazo em dias.", "motivo": "invalido"}],
        }

        texto = nodes._pergunta_do_parametro(faltante)

        self.assertIn("propor essa alteração", texto)
        self.assertIn("o valor informado para novo prazo em dias não parece válido", texto)


if __name__ == "__main__":
    unittest.main()
