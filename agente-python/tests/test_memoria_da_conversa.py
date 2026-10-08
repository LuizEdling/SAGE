"""Memória do que a conversa já conferiu e alterou.

Rodar de dentro do contêiner do agente:
    python -m unittest discover -s tests -t .
"""

import unittest
from unittest.mock import patch

from app.funcoes import consultas_pendentes, resumo_verificado
from app.graph import nodes

LER_B = {"id": 1, "codigo": "b.ler", "nome": "Consultar B", "tipo": "consulta", "descricao": "", "parametros": []}
LER_C = {"id": 2, "codigo": "c.ler", "nome": "Consultar C", "tipo": "consulta", "descricao": "", "parametros": []}
ALT_B = {"id": 3, "codigo": "b.alterar", "nome": "Alterar B", "tipo": "alteracao", "descricao": "", "parametros": []}
CATALOGO = [LER_B, LER_C, ALT_B]

LEU_B = {"function_id": 1, "tipo": "consulta", "status": "executada", "parametros": {}, "dados": {"ativa": False}}
ALTEROU_B = {
    "function_id": 3, "tipo": "alteracao", "status": "executada", "parametros": {"ativo": True},
    "antes": {"ativa": False}, "depois": {"ativa": True},
}


class ResumoVerificadoTest(unittest.TestCase):
    def test_sem_execucoes_nao_ha_resumo(self):
        self.assertIsNone(resumo_verificado([], CATALOGO))

    def test_resume_consulta_e_alteracao_com_o_nome_da_funcao(self):
        resumo = resumo_verificado([LEU_B, ALTEROU_B], CATALOGO)

        self.assertIn("Consulta feita «Consultar B»", resumo)
        self.assertIn('{"ativa": false}', resumo)
        self.assertIn("Alteração feita «Alterar B»", resumo)
        self.assertIn('antes {"ativa": false} → depois {"ativa": true}', resumo)

    def test_execucao_que_nao_foi_executada_fica_de_fora(self):
        falhou = {**LEU_B, "status": "falhou"}

        self.assertIsNone(resumo_verificado([falhou], CATALOGO))

    def test_dados_muito_longos_sao_cortados(self):
        enorme = {**LEU_B, "dados": {"lista": ["x" * 50] * 40}}

        linha = resumo_verificado([enorme], CATALOGO)

        self.assertLess(len(linha), 600)
        self.assertTrue(linha.endswith("…"))


class ConsultasPendentesTest(unittest.TestCase):
    def test_so_consultas_candidatas_ainda_nao_feitas(self):
        pendentes = consultas_pendentes([1, 2, 3], CATALOGO, [LEU_B])

        self.assertEqual([f["id"] for f in pendentes], [2])

    def test_sem_nada_feito_todas_as_consultas_estao_pendentes(self):
        self.assertEqual([f["id"] for f in consultas_pendentes([1, 2, 3], CATALOGO, [])], [1, 2])

    def test_funcao_fora_das_candidatas_nao_e_sugerida(self):
        self.assertEqual(consultas_pendentes([1], CATALOGO, []), [LER_B])


class RelatoTest(unittest.TestCase):
    def _estado(self, execucoes_anteriores, candidatas=(1, 2, 3)):
        return {
            "question": "x",
            "funcoes": CATALOGO,
            "funcoes_candidatas": list(candidatas),
            "execucoes_anteriores": execucoes_anteriores,
            "diagnostico": {"fontes": []},
            "modulos": [],
            "resultado_funcao": {
                "function_id": 3, "codigo": "b.alterar", "tipo": "alteracao", "status": "executada",
                "resultado": {"antes": {"ativa": False}, "depois": {"ativa": True}},
            },
        }

    def test_depois_da_alteracao_sugere_a_consulta_que_falta(self):
        novo = nodes.node_relatar_execucao(self._estado([LEU_B]))

        self.assertIn("Pronto, fiz a alteração: Alterar B.", novo["resposta_texto"])
        self.assertIn("ainda posso conferir: «Consultar C»", novo["resposta_texto"])
        self.assertFalse(novo["aguardando_esclarecimento"])

    def test_sem_consulta_pendente_nao_sugere_nada(self):
        novo = nodes.node_relatar_execucao(self._estado([LEU_B], candidatas=(1, 3)))

        self.assertNotIn("ainda posso conferir", novo["resposta_texto"])

    def test_relato_de_alteracao_negada_nao_sugere(self):
        estado = self._estado([LEU_B])
        estado["resultado_funcao"] = {**estado["resultado_funcao"], "status": "negada"}

        novo = nodes.node_relatar_execucao(estado)

        self.assertNotIn("ainda posso conferir", novo["resposta_texto"])


class MemoriaNosPromptsTest(unittest.TestCase):
    def test_decidir_recebe_o_que_ja_foi_verificado(self):
        estado = {
            "question": "e a outra?", "regras": [], "contexto_erp": {}, "funcoes": CATALOGO,
            "execucoes_anteriores": [LEU_B],
        }

        with patch.object(nodes, "decidir_funcao", return_value=None) as modelo:
            nodes._decidir(estado, [LER_B, LER_C])

        self.assertIn("Consulta feita «Consultar B»", modelo.call_args.kwargs["ja_verificado"])

    def test_sem_memoria_o_prompt_nao_recebe_nada(self):
        estado = {"question": "x", "regras": [], "contexto_erp": {}, "funcoes": CATALOGO}

        with patch.object(nodes, "decidir_funcao", return_value=None) as modelo:
            nodes._decidir(estado, [LER_B])

        self.assertIsNone(modelo.call_args.kwargs["ja_verificado"])


class NaoRepeteConsultaTest(unittest.TestCase):
    def _estado(self, pergunta="e a outra?"):
        return {
            "question": pergunta, "regras": [], "contexto_erp": {}, "funcoes": CATALOGO,
            "execucoes_anteriores": [LEU_B],
        }

    def _modelo_escolhendo(self, *ids):
        return [{"function_id": i, "parametros": {}, "motivo": "m"} for i in ids]

    def test_escolha_repetida_cai_para_a_consulta_que_falta(self):
        with patch.object(nodes, "decidir_funcao", side_effect=self._modelo_escolhendo(1, 2)) as modelo:
            escolha, _ = nodes._decidir(self._estado(), [LER_B, LER_C])

        self.assertEqual(escolha[0]["id"], 2)
        self.assertEqual([f["id"] for f in modelo.call_args.kwargs["candidatas"]], [2])

    def test_repetida_sem_outra_opcao_nao_executa_nada(self):
        with patch.object(nodes, "decidir_funcao", side_effect=self._modelo_escolhendo(1)):
            self.assertEqual(nodes._decidir(self._estado(), [LER_B]), (None, None))

    def test_pedido_explicito_para_conferir_de_novo_repete(self):
        with patch.object(nodes, "decidir_funcao", side_effect=self._modelo_escolhendo(1)):
            escolha, _ = nodes._decidir(self._estado("confere de novo?"), [LER_B, LER_C])

        self.assertEqual(escolha[0]["id"], 1)

    def test_parametros_diferentes_nao_sao_repeticao(self):
        com_parametro = {**LER_B, "parametros": [{"nome": "x", "tipo": "texto", "obrigatorio": True, "descricao": ""}]}
        leu = {**LEU_B, "parametros": {"x": "a"}}
        estado = {**self._estado(), "execucoes_anteriores": [leu]}

        with patch.object(nodes, "decidir_funcao", return_value={"function_id": 1, "parametros": {"x": "b"}, "motivo": "m"}):
            escolha, _ = nodes._decidir(estado, [com_parametro])

        self.assertEqual(escolha[1], {"x": "b"})


if __name__ == "__main__":
    unittest.main()
